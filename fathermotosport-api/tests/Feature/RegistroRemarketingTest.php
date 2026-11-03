<?php

namespace Tests\Feature;

use App\Http\Requests\Concerns\DatosPersonales;
use App\Models\EmailVerificationCode;
use App\Models\Role;
use App\Models\User;
use App\Services\ResolverMx;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Registro de la fase 1 de remarketing: teléfono con código de país y país obligatorios,
 * fecha de nacimiento opcional (no futura, 18 años o más), casilla "Tengo 18 años o más"
 * solo sin fecha, idioma del cliente y correos sin MX o desechables rechazados.
 */
class RegistroRemarketingTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'nueva.clienta@gmail.com';

    private const CLAVE = 'Clave#Segura1';

    /** @var array<string, bool> dominio => tiene MX (sin red) */
    private array $mx = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);

        $mx = &$this->mx;
        $this->app->instance(ResolverMx::class, new class($mx) extends ResolverMx
        {
            public function __construct(private array &$mx) {}

            public function tieneMx(string $dominio): bool
            {
                return $this->mx[$dominio] ?? true;
            }
        });
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'email' => self::EMAIL,
            'first_name' => 'Nueva',
            'last_name' => 'Clienta',
            'country' => 'BO',
            'phone' => '+591 7654-3210',
            'adult_confirmed' => true,
            'locale' => 'pt',
            'password' => self::CLAVE,
            'password_confirmation' => self::CLAVE,
        ], $cambios);
    }

    private function registrar(array $cambios = [], array $cabeceras = [])
    {
        $this->postJson('/api/v1/auth/send-verification-code', ['email' => $cambios['email'] ?? self::EMAIL, 'recaptcha_token' => 'ok'])->assertOk();
        $codigo = EmailVerificationCode::where('email', $cambios['email'] ?? self::EMAIL)->latest('id')->firstOrFail()->code;

        return $this->withHeaders($cabeceras)->postJson('/api/v1/auth/verify-and-register', $this->datos([...$cambios, 'code' => $codigo]));
    }

    private function fechaConEdad(int $anios, int $diasExtra = 0): string
    {
        return DatosPersonales::hoy()->subYears($anios)->addDays($diasExtra)->toDateString();
    }

    public function test_crea_la_cuenta_con_telefono_internacional_pais_idioma_y_declaracion_de_edad(): void
    {
        $this->registrar()->assertCreated();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame('+59176543210', $user->phone);
        $this->assertSame('BO', $user->country);
        $this->assertSame('pt', $user->locale);
        $this->assertNull($user->birth_date);
        $this->assertSame('declared', $user->adult_confirmation);
        $this->assertNotNull($user->adult_confirmed_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('code', $user->email_verified_via);
    }

    public function test_telefono_y_pais_son_obligatorios(): void
    {
        $this->registrar(['phone' => '', 'country' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'country']);

        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public static function telefonosInvalidos(): array
    {
        return [
            'sin código de país' => ['76543210'],
            'muy corto' => ['+591 765'],
            'muy largo' => ['+591 7654321098765432'],
            'con letras' => ['+591 7654abc'],
            'prefijo inexistente' => ['+999 76543210'],
        ];
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_el_telefono_debe_llevar_codigo_de_pais(string $telefono): void
    {
        $this->registrar(['phone' => $telefono])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_un_pais_inexistente_se_rechaza(): void
    {
        $this->registrar(['country' => 'XX'])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    public function test_una_fecha_de_nacimiento_futura_se_rechaza(): void
    {
        $this->registrar(['birth_date' => DatosPersonales::hoy()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonPath('errors.birth_date.0', 'La fecha de nacimiento no puede ser futura.');
    }

    public function test_un_menor_de_18_se_rechaza_aunque_marque_la_casilla(): void
    {
        $this->registrar(['birth_date' => $this->fechaConEdad(18, 1), 'adult_confirmed' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.birth_date.0', 'Debes tener 18 años o más para crear una cuenta.');
    }

    public function test_cumplir_18_hoy_cuenta_como_mayor_de_edad(): void
    {
        $fecha = $this->fechaConEdad(18);

        $this->registrar(['birth_date' => $fecha, 'adult_confirmed' => null])->assertCreated();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame($fecha, $user->birth_date->toDateString());
        $this->assertSame('birth_date', $user->adult_confirmation);
    }

    public function test_sin_fecha_la_casilla_es_obligatoria(): void
    {
        $this->registrar(['birth_date' => null, 'adult_confirmed' => null])
            ->assertStatus(422)
            ->assertJsonPath('errors.adult_confirmed.0', 'Confirma que tienes 18 años o más.');

        $this->registrar(['birth_date' => '', 'adult_confirmed' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('adult_confirmed');
    }

    public function test_con_fecha_la_casilla_se_ignora(): void
    {
        $this->registrar(['birth_date' => '1990-05-20', 'adult_confirmed' => false])->assertCreated();

        $this->assertSame('birth_date', User::where('email', self::EMAIL)->value('adult_confirmation'));
    }

    public function test_los_mensajes_salen_en_el_idioma_de_la_web(): void
    {
        $this->registrar(['phone' => ''], ['X-Locale' => 'pt'])
            ->assertJsonPath('errors.phone.0', 'O telefone é obrigatório.');

        $this->registrar(['phone' => ''], ['X-Locale' => 'en'])
            ->assertJsonPath('errors.phone.0', 'Phone number is required.');
    }

    public function test_un_dominio_sin_mx_no_recibe_codigo(): void
    {
        $this->mx['gmail.com'] = false;

        $this->postJson('/api/v1/auth/send-verification-code', ['email' => self::EMAIL, 'recaptcha_token' => 'ok'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Ese dominio no recibe correos. Revisa que el correo esté bien escrito.');

        $this->assertDatabaseCount('email_verification_codes', 0);
        Mail::assertNothingSent();
    }

    public function test_un_correo_desechable_se_rechaza(): void
    {
        $this->postJson('/api/v1/auth/send-verification-code', ['email' => 'alguien@mailinator.com', 'recaptcha_token' => 'ok'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'No aceptamos correos temporales o desechables. Usa tu correo habitual.');

        Mail::assertNothingSent();
    }

    public function test_el_primer_paso_avisa_antes_de_mandar_el_codigo_si_los_datos_estan_mal(): void
    {
        $this->postJson('/api/v1/auth/send-verification-code', [
            'email' => self::EMAIL, 'recaptcha_token' => 'ok', 'phone' => '7654', 'birth_date' => $this->fechaConEdad(10),
        ])->assertStatus(422)->assertJsonValidationErrors(['phone', 'birth_date']);

        Mail::assertNothingSent();
    }

    public function test_iniciar_sesion_guarda_el_idioma(): void
    {
        $user = User::create([
            'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
            'first_name' => 'Ana', 'last_name' => 'Perez', 'email' => 'ana@gmail.com',
            'password' => Hash::make(self::CLAVE), 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@gmail.com', 'password' => self::CLAVE, 'locale' => 'en'])->assertOk();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_una_cuenta_de_google_queda_verificada_y_se_le_piden_los_datos(): void
    {
        $googleUser = (new SocialiteUser)->setRaw(['given_name' => 'Gugle', 'family_name' => 'User'])
            ->map(['id' => 'g-1', 'name' => 'Gugle User', 'email' => 'gugle@gmail.com', 'avatar' => null]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/v1/auth/google/callback')->assertRedirect();

        $user = User::where('email', 'gugle@gmail.com')->firstOrFail();
        $this->assertSame('google', $user->email_verified_via);
        $this->assertNull($user->motivoSinPromociones());
        $this->assertTrue($user->debeVerVentanaDePerfil(), 'le faltan teléfono y país');
    }
}
