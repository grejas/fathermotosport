<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Mail\PromocionSimple;
use App\Models\MailUnsubscribe;
use App\Models\Role;
use App\Models\User;
use App\Support\BajaDePromociones;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Baja de las promociones: enlace firmado (HMAC), página de la web que pide confirmar,
 * POST de un clic (List-Unsubscribe-Post) y registro de cada baja. Los transaccionales
 * no llevan baja.
 */
class BajaPromocionesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/promos/unsubscribe';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.frontend_url' => 'https://web.test']);
    }

    private function cliente(array $datos = []): User
    {
        $user = User::create([
            'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
            'first_name' => 'Juan', 'last_name' => 'Perez', 'email' => 'juan.perez@gmail.com',
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active', 'email_verified_at' => now(),
            ...$datos,
        ]);
        // Verificado con evidencia (código): sin ella no recibiría promociones.
        $user->forceFill(['email_verified_via' => 'code'])->save();

        return $user;
    }

    private function enlace(User $user, ?string $firma = null): string
    {
        return self::URL.'?'.http_build_query(['u' => $user->id, 't' => $firma ?? BajaDePromociones::firma($user)]);
    }

    public function test_abrir_el_enlace_consulta_pero_no_da_de_baja(): void
    {
        $user = $this->cliente();

        $this->getJson($this->enlace($user))
            ->assertOk()
            ->assertJson(['valid' => true, 'email' => 'ju••••••••@gmail.com', 'unsubscribed' => false]);

        $this->assertNull($user->fresh()->promo_unsubscribed_at);
        $this->assertDatabaseCount('mail_unsubscribes', 0);
    }

    public function test_una_firma_invalida_no_sirve(): void
    {
        $user = $this->cliente();
        $otro = $this->cliente(['email' => 'otro@gmail.com']);

        $this->getJson($this->enlace($user, 'firma-falsa'))->assertNotFound();
        // La firma de otro cliente no vale para este.
        $this->postJson($this->enlace($user, BajaDePromociones::firma($otro)))->assertNotFound();
        $this->postJson(self::URL)->assertNotFound();

        $this->assertNull($user->fresh()->promo_unsubscribed_at);
        $this->assertDatabaseCount('mail_unsubscribes', 0);
    }

    public function test_el_boton_de_la_web_da_de_baja_y_lo_registra(): void
    {
        $user = $this->cliente();

        $this->postJson(self::URL, ['u' => $user->id, 't' => BajaDePromociones::firma($user), 'ref' => 'campaign:7'])
            ->assertOk()->assertJson(['unsubscribed' => true]);

        $this->assertNotNull($user->fresh()->promo_unsubscribed_at);
        $this->assertSame('dado de baja', $user->fresh()->motivoSinPromociones());
        $this->assertDatabaseHas('mail_unsubscribes', [
            'user_id' => $user->id, 'email' => 'juan.perez@gmail.com', 'source' => 'link', 'reference' => 'campaign:7',
        ]);
    }

    public function test_el_un_clic_del_cliente_de_correo_da_de_baja_sin_pedir_nada_mas(): void
    {
        $user = $this->cliente();

        // Así lo manda Gmail: POST a la URL de List-Unsubscribe con un formulario.
        $this->post($this->enlace($user), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertNotNull($user->fresh()->promo_unsubscribed_at);
        $this->assertSame('one_click', MailUnsubscribe::sole()->source);
    }

    public function test_darse_de_baja_dos_veces_no_duplica_el_registro_ni_cambia_la_fecha(): void
    {
        $user = $this->cliente();

        $this->postJson($this->enlace($user))->assertOk();
        $fecha = $user->fresh()->promo_unsubscribed_at;
        $this->travel(5)->minutes();
        $this->postJson($this->enlace($user))->assertOk();

        $this->assertEquals($fecha, $user->fresh()->promo_unsubscribed_at);
        $this->assertDatabaseCount('mail_unsubscribes', 1);
        $this->getJson($this->enlace($user))->assertJsonPath('unsubscribed', true);
    }

    public function test_el_correo_promocional_lleva_baja_firmada_y_cabeceras_de_un_clic(): void
    {
        $user = $this->cliente(['locale' => 'pt']);
        $correo = new PromocionSimple($user, 'campaign:7', [
            'es' => ['asunto' => 'Oferta', 'titulo' => 'Hola', 'texto' => 'Texto'],
            'pt' => ['asunto' => 'Oferta PT', 'titulo' => 'Olá', 'texto' => 'Texto PT'],
        ]);

        $html = $correo->render();
        $this->assertStringContainsString(e(BajaDePromociones::urlPagina($user)), $html);
        $this->assertStringContainsString('https://web.test/pt/baja?', BajaDePromociones::urlPagina($user));
        $this->assertStringContainsString('Cancelar o recebimento de promoções', $html);
        $this->assertStringContainsString('Olá', $html);

        $cabeceras = $correo->headers()->text;
        $this->assertSame('<'.BajaDePromociones::urlUnClic($user).'>', $cabeceras['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $cabeceras['List-Unsubscribe-Post']);
        $this->assertSame('campaign:7:'.$user->id, $cabeceras['Resend-Idempotency-Key']);
    }

    public function test_los_correos_transaccionales_no_llevan_baja(): void
    {
        $html = (new EmailVerificationCodeMail('123456'))->render();

        $this->assertStringNotContainsString('unsubscribe', $html);
        $this->assertStringNotContainsString('Cancelar suscripción', $html);
        $this->assertStringNotContainsString('/baja', $html);
    }
}
