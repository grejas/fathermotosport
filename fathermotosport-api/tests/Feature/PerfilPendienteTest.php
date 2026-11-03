<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\ResolverMx;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Clientes antiguos o de Google sin teléfono, país o correo verificado: se les pide una
 * vez, en una ventana que se puede cerrar, y nunca se les impide comprar.
 */
class PerfilPendienteTest extends TestCase
{
    use RefreshDatabase;

    private bool $hayMx = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $hayMx = &$this->hayMx;
        $this->app->instance(ResolverMx::class, new class($hayMx) extends ResolverMx
        {
            public function __construct(private bool &$hayMx) {}

            public function tieneMx(string $dominio): bool
            {
                return $this->hayMx;
            }
        });
    }

    private function clienteAntiguo(array $datos = []): User
    {
        return User::create([
            'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
            'first_name' => 'Antiguo', 'last_name' => 'Cliente', 'email' => 'antiguo@gmail.com',
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active', 'email_verified_at' => null,
            ...$datos,
        ]);
    }

    public function test_la_ventana_se_pide_una_vez_y_se_puede_cerrar_sin_completar(): void
    {
        Sanctum::actingAs($user = $this->clienteAntiguo());

        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.profile_incomplete', true)
            ->assertJsonPath('data.profile_prompt_due', true);

        $this->postJson('/api/v1/user/profile-prompt/dismiss')->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.profile_incomplete', true)
            ->assertJsonPath('data.profile_prompt_due', false);
        $this->assertNull($user->fresh()->phone);
    }

    public function test_completar_datos_guarda_telefono_internacional_pais_y_edad(): void
    {
        Sanctum::actingAs($user = $this->clienteAntiguo());

        $this->postJson('/api/v1/user/complete-profile', ['phone' => '76543210', 'country' => 'BO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'adult_confirmed']);

        $this->postJson('/api/v1/user/complete-profile', [
            'phone' => '+55 11 99999-8888', 'country' => 'br', 'birth_date' => '1990-01-15',
        ])->assertStatus(422)->assertJsonValidationErrors('country');

        $this->postJson('/api/v1/user/complete-profile', [
            'phone' => '+55 11 99999-8888', 'country' => 'BR', 'birth_date' => '1990-01-15',
        ])->assertOk()->assertJsonPath('user.profile_prompt_due', false);

        $user->refresh();
        $this->assertSame('+5511999998888', $user->phone);
        $this->assertSame('BR', $user->country);
        $this->assertSame('birth_date', $user->adult_confirmation);
        $this->assertNull($user->email_verified_at, 'completar datos no verifica el correo');
    }

    public function test_verificar_el_correo_con_codigo_lo_habilita_para_promociones(): void
    {
        Sanctum::actingAs($user = $this->clienteAntiguo(['phone' => '+59176543210', 'country' => 'BO']));
        $this->assertSame('correo no verificado', $user->motivoSinPromociones());

        $this->postJson('/api/v1/user/email/send-code')->assertOk();
        Mail::assertSent(EmailVerificationCodeMail::class, fn ($m) => $m->hasTo('antiguo@gmail.com'));

        $this->postJson('/api/v1/user/email/verify', ['code' => '000000'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);

        $codigo = EmailVerificationCode::where('email', 'antiguo@gmail.com')->latest('id')->firstOrFail()->code;
        $this->postJson('/api/v1/user/email/verify', ['code' => $codigo])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('code', $user->email_verified_via);
        $this->assertNull($user->motivoSinPromociones());

        $this->postJson('/api/v1/user/email/send-code')->assertStatus(409);
    }

    public function test_un_correo_sin_mx_no_recibe_el_codigo(): void
    {
        $this->hayMx = false;
        Sanctum::actingAs($this->clienteAntiguo());

        $this->postJson('/api/v1/user/email/send-code')->assertStatus(422)->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_verification_codes', 0);
    }

    public function test_sin_datos_puede_comprar_y_al_pagar_se_completan_desde_la_direccion(): void
    {
        Sanctum::actingAs($user = $this->clienteAntiguo());
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 10]);

        $this->postJson('/api/v1/orders', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Antiguo Cliente', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->assertCreated();

        $user->refresh();
        $this->assertSame('+59176543210', $user->phone);
        $this->assertSame('BO', $user->country);
    }

    public function test_al_pagar_no_se_pisa_un_telefono_que_ya_tenia(): void
    {
        Sanctum::actingAs($user = $this->clienteAntiguo(['phone' => '+5511999998888']));
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 10]);

        $this->postJson('/api/v1/orders', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Antiguo Cliente', 'phone' => '76543210', 'country' => 'Estados Unidos',
                'city' => 'Miami', 'address_line' => 'Ocean Dr 1',
            ],
            'payment_method' => 'paypal',
        ])->assertCreated();

        $user->refresh();
        $this->assertSame('+5511999998888', $user->phone);
        $this->assertSame('US', $user->country);
    }

    public function test_telefono_y_fecha_no_salen_en_apis_publicas(): void
    {
        $this->clienteAntiguo(['phone' => '+59176543210', 'birth_date' => '1990-01-15', 'country' => 'BO']);

        foreach (['/api/v1/reviews', '/api/v1/store/settings', '/api/v1/products'] as $url) {
            $body = $this->getJson($url)->getContent();
            $this->assertStringNotContainsString('76543210', $body, $url);
            $this->assertStringNotContainsString('1990-01-15', $body, $url);
        }
    }
}
