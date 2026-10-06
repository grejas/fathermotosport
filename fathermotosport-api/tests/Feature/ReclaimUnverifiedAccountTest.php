<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider as Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Una cuenta creada desde un pedido con tarjeta lleva un email que nadie comprobó: quien
 * compró pudo escribir uno ajeno. El dueño real de ese email tiene que poder quedarse
 * con la cuenta probando que controla la casilla, y la contraseña que puso el otro
 * tiene que dejar de servir.
 */
class ReclaimUnverifiedAccountTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'duenio.real@gmail.com';

    private const CLAVE_AJENA = 'Ajena#2026';

    private const CLAVE_NUEVA = 'Propia#2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);
    }

    /** Como queda tras registerFromOrder con un pedido de tarjeta: sin verificar. */
    private function cuentaSinVerificar(string $rol = 'cliente'): User
    {
        return User::create([
            'role_id' => Role::where('slug', $rol)->firstOrFail()->id,
            'first_name' => 'Impostor',
            'last_name' => 'X',
            'email' => self::EMAIL,
            'password' => Hash::make(self::CLAVE_AJENA),
            'status' => 'active',
            'email_verified_at' => null,
        ]);
    }

    private function pedirCodigo()
    {
        return $this->postJson('/api/v1/auth/send-verification-code', [
            'email' => self::EMAIL,
            'recaptcha_token' => 'ok',
        ]);
    }

    private function registrar(string $codigo)
    {
        return $this->postJson('/api/v1/auth/verify-and-register', [
            'email' => self::EMAIL,
            'code' => $codigo,
            'first_name' => 'Duenio',
            'last_name' => 'Real',
            'country' => 'BO',
            'password' => self::CLAVE_NUEVA,
            'password_confirmation' => self::CLAVE_NUEVA,
        ]);
    }

    private function codigoEnviado(): string
    {
        return EmailVerificationCode::where('email', self::EMAIL)->latest('id')->firstOrFail()->code;
    }

    private function pedidoDe(User $user, string $metodo, ?string $verificadoPor): Order
    {
        return Order::create([
            'user_id' => $user->id, 'status' => 'processing', 'subtotal' => 100, 'discount' => 0,
            'shipping' => 0, 'tax' => 0, 'total' => 100, 'payment_status' => 'paid',
            'shipping_status' => 'pending', 'payment_method' => $metodo, 'country' => 'Bolivia',
            'guest_email' => self::EMAIL, 'email_verificado_por' => $verificadoPor,
        ]);
    }

    private function assertPedidosTrasElReclamo(User $cuenta, Order $deTarjeta, Order $dePaypal): void
    {
        // El de tarjeta pudo hacerlo un tercero: vuelve a ser de invitado, sin perderse.
        $this->assertNull($deTarjeta->fresh()->user_id);
        $this->assertSame(self::EMAIL, $deTarjeta->fresh()->guest_email);
        // El de PayPal lleva un email confirmado por PayPal: se queda en la cuenta.
        $this->assertSame($cuenta->id, $dePaypal->fresh()->user_id);
    }

    /** Socialite devuelve este usuario de Google sin salir a la red. */
    private function simularGoogle(string $email): void
    {
        $googleUser = (new SocialiteUser)->setRaw([
            'given_name' => 'Duenio', 'family_name' => 'Real',
        ])->map([
            'id' => 'google-123', 'name' => 'Duenio Real', 'email' => $email, 'avatar' => null,
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function login(string $clave)
    {
        return $this->postJson('/api/v1/auth/login', ['email' => self::EMAIL, 'password' => $clave]);
    }

    public function test_the_real_owner_reclaims_the_account_by_verifying_the_code(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $cuenta->createToken('auth');

        $this->pedirCodigo()->assertOk();
        $this->registrar($this->codigoEnviado())
            ->assertCreated()
            ->assertJsonPath('user.id', $cuenta->id)
            ->assertJsonPath('user.first_name', 'Duenio')
            ->assertJsonStructure(['token', 'welcome_coupon' => ['code', 'value']]);

        // Misma cuenta, no una duplicada, ahora verificada.
        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $cuenta->refresh();
        $this->assertNotNull($cuenta->email_verified_at);
        $this->assertSame('Real', $cuenta->last_name);

        // La contraseña anterior ya no entra; la nueva sí.
        $this->login(self::CLAVE_AJENA)->assertStatus(422);
        $this->login(self::CLAVE_NUEVA)->assertOk();

        // La sesión que tenía abierta quien creó la cuenta se cerró: solo quedan la del
        // reclamo y la del login recién hecho.
        $this->assertSame(2, PersonalAccessToken::where('tokenable_id', $cuenta->id)->count());
    }

    public function test_reclaiming_with_the_code_unlinks_card_orders_and_keeps_paypal_ones(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $deTarjeta = $this->pedidoDe($cuenta, 'stripe', null);
        $dePaypal = $this->pedidoDe($cuenta, 'paypal', 'paypal');

        $this->pedirCodigo()->assertOk();
        $this->registrar($this->codigoEnviado())->assertCreated();

        $this->assertPedidosTrasElReclamo($cuenta, $deTarjeta, $dePaypal);
    }

    public function test_reclaiming_with_google_verifies_the_account_and_unlinks_card_orders(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $cuenta->createToken('auth');
        $deTarjeta = $this->pedidoDe($cuenta, 'stripe', null);
        $dePaypal = $this->pedidoDe($cuenta, 'paypal', 'paypal');

        $this->simularGoogle(self::EMAIL);

        $this->get('/api/v1/auth/google/callback')
            ->assertRedirectContains('/auth/google/callback?token=');

        $cuenta->refresh();
        $this->assertNotNull($cuenta->email_verified_at);
        $this->login(self::CLAVE_AJENA)->assertStatus(422);
        // Solo queda el token que acaba de emitir el callback.
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $cuenta->id)->count());
        $this->assertPedidosTrasElReclamo($cuenta, $deTarjeta, $dePaypal);
    }

    public function test_google_does_not_touch_an_employee_without_verified_email(): void
    {
        $empleado = $this->cuentaSinVerificar('empleado');
        $pedido = $this->pedidoDe($empleado, 'stripe', null);

        $this->simularGoogle(self::EMAIL);
        $this->get('/api/v1/auth/google/callback')->assertRedirect();

        // Ni su contraseña ni sus pedidos cambian: el reclamo es solo para clientes.
        $this->login(self::CLAVE_AJENA)->assertOk();
        $this->assertSame($empleado->id, $pedido->fresh()->user_id);
    }

    public function test_a_wrong_code_does_not_reclaim_anything(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $this->pedirCodigo()->assertOk();

        $this->registrar('000000')->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertNull($cuenta->fresh()->email_verified_at);
        $this->login(self::CLAVE_AJENA)->assertOk();
    }

    public function test_a_verified_account_still_blocks_the_registration(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $cuenta->update(['email_verified_at' => now()]);

        $this->pedirCodigo()->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_staff_accounts_are_never_reclaimable(): void
    {
        // Un empleado sin email verificado no se puede tomar por el registro de clientes.
        $this->cuentaSinVerificar('empleado');

        $this->pedirCodigo()->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_deleted_account_still_blocks_the_registration(): void
    {
        $this->cuentaSinVerificar()->delete();

        // El email sigue ocupado en la tabla: dejarlo pasar terminaría en un error 500.
        $this->pedirCodigo()->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_works_for_an_unverified_account_and_verifies_it(): void
    {
        $cuenta = $this->cuentaSinVerificar();
        $cuenta->createToken('auth');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => self::EMAIL])->assertOk();

        // El enlace sale aunque el email no esté verificado.
        $this->assertDatabaseHas('password_reset_tokens', ['email' => self::EMAIL]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => self::EMAIL,
            'token' => Password::createToken($cuenta),
            'password' => self::CLAVE_NUEVA,
            'password_confirmation' => self::CLAVE_NUEVA,
        ])->assertOk();

        $cuenta->refresh();
        $this->assertNotNull($cuenta->email_verified_at, 'usar el enlace del correo prueba la casilla');
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $cuenta->id)->count());
        $this->login(self::CLAVE_AJENA)->assertStatus(422);
        $this->login(self::CLAVE_NUEVA)->assertOk();
    }
}
