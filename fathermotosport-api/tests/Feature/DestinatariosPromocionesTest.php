<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Mail\PromocionSimple;
use App\Models\Role;
use App\Models\User;
use App\Services\EnvioPromocional;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Correo válido" = existe y es de su dueño: solo las cuentas con email_verified_at
 * reciben promociones. Las demás se omiten ANTES de llamar a Resend, sin gastar cupo.
 */
class DestinatariosPromocionesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_10_29_200001_add_remarketing_fields_to_users_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
    }

    private function cliente(string $email, array $datos = []): User
    {
        // forceFill: la baja, el rebote y la queja no se asignan en masa a propósito.
        $user = (new User)->forceFill([
            'role_id' => Role::where('slug', $datos['rol'] ?? 'cliente')->firstOrFail()->id,
            'first_name' => 'Cliente', 'last_name' => 'Prueba', 'email' => $email,
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active', 'email_verified_at' => now(),
            // Por defecto, verificado con evidencia (código); los tests de "sin evidencia" lo vacían.
            'email_verified_via' => 'code',
            ...array_diff_key($datos, ['rol' => true]),
        ]);
        $user->save();

        return $user;
    }

    private function promo(User $user): PromocionSimple
    {
        return new PromocionSimple($user, 'campaign:1', ['es' => ['asunto' => 'Oferta', 'titulo' => 'Hola', 'texto' => 'Texto']]);
    }

    public function test_solo_reciben_los_clientes_validos_y_cada_correo_una_vez(): void
    {
        $verificado = $this->cliente('verificado@gmail.com');
        $google = $this->cliente('google@gmail.com', ['email_verified_via' => 'google']);
        $this->cliente('inventado@gmail.com', ['email_verified_at' => null]);
        // Verificado en la base pero sin código, Google ni origen registrado: fuera.
        $this->cliente('sinrastro@gmail.com', ['email_verified_via' => null]);
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $this->cliente('inactivo@gmail.com', ['status' => 'inactive']);
        $this->cliente('bloqueado@gmail.com', ['status' => 'banned']);
        $this->cliente('borrado@gmail.com')->delete();
        $this->cliente('rebote@gmail.com', ['email_bounced_at' => now()]);
        $this->cliente('queja@gmail.com', ['email_complained_at' => now()]);
        $this->cliente('empleado@gmail.com', ['rol' => 'empleado']);
        $this->cliente('mal formato@gmail', []);
        // Mismo correo con otras mayúsculas (SQLite lo permite): va una sola vez, a la más antigua.
        $this->travel(1)->minutes();
        $this->cliente('Verificado@Gmail.com');

        $destinatarios = User::destinatariosDePromociones()->pluck('email')->all();

        $this->assertEqualsCanonicalizing(['verificado@gmail.com', 'google@gmail.com'], $destinatarios);
        $this->assertNull($verificado->motivoSinPromociones());
        $this->assertNull($google->motivoSinPromociones());
    }

    public function test_cada_exclusion_tiene_su_motivo_para_el_historial(): void
    {
        $this->assertSame('correo no verificado', $this->cliente('a@gmail.com', ['email_verified_at' => null])->motivoSinPromociones());
        $this->assertSame('correo sin evidencia de verificación', $this->cliente('h@gmail.com', ['email_verified_via' => null])->motivoSinPromociones());
        $this->assertSame('dado de baja', $this->cliente('b@gmail.com', ['promo_unsubscribed_at' => now()])->motivoSinPromociones());
        $this->assertSame('cuenta inactiva', $this->cliente('c@gmail.com', ['status' => 'inactive'])->motivoSinPromociones());
        $this->assertSame('rebote', $this->cliente('d@gmail.com', ['email_bounced_at' => now()])->motivoSinPromociones());
        $this->assertSame('queja de spam', $this->cliente('e@gmail.com', ['email_complained_at' => now()])->motivoSinPromociones());
        $this->assertSame('no es cliente', $this->cliente('f@gmail.com', ['rol' => 'empleado'])->motivoSinPromociones());

        $borrado = $this->cliente('g@gmail.com');
        $borrado->delete();
        $this->assertSame('cuenta borrada', $borrado->motivoSinPromociones());
    }

    public function test_un_no_verificado_se_omite_sin_llamar_a_resend_ni_gastar_cupo(): void
    {
        $envio = app(EnvioPromocional::class);

        $resultado = $envio->enviar($this->promo($this->cliente('miguel23@gmail.com', ['email_verified_at' => null])));

        $this->assertSame(['estado' => 'omitido', 'motivo' => 'omitido: correo no verificado'], $resultado);
        Mail::assertNothingSent();
        $this->assertSame(0, $envio->enviadosHoy());
    }

    public function test_un_verificado_recibe_y_cuenta_en_el_cupo(): void
    {
        $envio = app(EnvioPromocional::class);

        $this->assertSame('enviado', $envio->enviar($this->promo($this->cliente('ok@gmail.com')))['estado']);
        $this->assertSame('enviado', $envio->enviar($this->promo($this->cliente('gg@gmail.com', ['email_verified_via' => 'google'])))['estado']);

        Mail::assertSent(PromocionSimple::class, 2);
        $this->assertSame(2, $envio->enviadosHoy());
    }

    public function test_dado_de_baja_borrado_o_inactivo_no_reciben(): void
    {
        $envio = app(EnvioPromocional::class);
        $borrado = $this->cliente('borrado@gmail.com');
        $borrado->delete();

        foreach ([
            $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]),
            $this->cliente('inactivo@gmail.com', ['status' => 'inactive']),
            $borrado,
        ] as $user) {
            $this->assertSame('omitido', $envio->enviar($this->promo($user))['estado'], $user->email);
        }

        Mail::assertNothingSent();
        $this->assertSame(0, $envio->enviadosHoy());
    }

    public function test_el_tope_diario_pospone_y_solo_cuentan_los_enviados(): void
    {
        config(['marketing.limits.per_day' => 2]);
        $envio = app(EnvioPromocional::class);

        $envio->enviar($this->promo($this->cliente('uno@gmail.com')));
        $envio->enviar($this->promo($this->cliente('sinverificar@gmail.com', ['email_verified_at' => null])));
        $envio->enviar($this->promo($this->cliente('dos@gmail.com')));
        $tercero = $envio->enviar($this->promo($this->cliente('tres@gmail.com')));

        $this->assertSame('pospuesto', $tercero['estado']);
        Mail::assertSent(PromocionSimple::class, 2);
        $this->assertSame(2, $envio->enviadosHoy());
    }

    public function test_el_limite_por_minuto_pospone(): void
    {
        config(['marketing.limits.per_minute' => 1]);
        $envio = app(EnvioPromocional::class);

        $this->assertSame('enviado', $envio->enviar($this->promo($this->cliente('uno@gmail.com')))['estado']);
        $this->assertSame('pospuesto', $envio->enviar($this->promo($this->cliente('dos@gmail.com')))['estado']);
    }

    public function test_el_admin_marca_un_correo_como_verificado_con_registro_de_quien_y_cuando(): void
    {
        $admin = User::where('email', 'admin@fathermotosport.com')->firstOrFail();
        $cliente = $this->cliente('verificar@gmail.com', ['email_verified_at' => null]);
        $this->actingAs($admin);

        Livewire::test(ListCustomers::class)
            ->assertTableActionVisible('marcar_verificado', $cliente)
            ->callTableAction('marcar_verificado', $cliente)
            ->assertNotified('Correo marcado como verificado');

        $cliente->refresh();
        $this->assertNotNull($cliente->email_verified_at);
        $this->assertSame('admin', $cliente->email_verified_via);
        $this->assertSame($admin->id, $cliente->email_verified_by);
        $this->assertNull($cliente->motivoSinPromociones());
    }

    public function test_un_empleado_no_puede_marcar_correos_como_verificados(): void
    {
        $empleado = $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado']);
        $cliente = $this->cliente('verificar@gmail.com', ['email_verified_at' => null]);
        $this->actingAs($empleado);

        Livewire::test(ListCustomers::class)->assertTableActionHidden('marcar_verificado', $cliente);
    }

    public function test_la_migracion_se_puede_reintentar_y_su_down_deshace_todo(): void
    {
        $migracion = require database_path('migrations/'.self::MIGRACION);
        $cliente = $this->cliente('dato@gmail.com', ['locale' => 'pt']);

        $migracion->up();
        $this->assertSame('pt', $cliente->fresh()->locale, 'reintentar no toca los datos');

        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('users', 'locale'));
        $this->assertFalse(Schema::hasTable('mail_unsubscribes'));
        $this->assertSame('dato@gmail.com', DB::table('users')->where('id', $cliente->id)->value('email'));

        $migracion->up();
        $migracion->up();
        foreach (['locale', 'adult_confirmation', 'adult_confirmed_at', 'email_verified_via', 'email_verified_by', 'profile_prompted_at', 'promo_unsubscribed_at', 'email_bounced_at', 'email_complained_at'] as $columna) {
            $this->assertTrue(Schema::hasColumn('users', $columna), $columna);
        }
        $this->assertTrue(Schema::hasTable('mail_unsubscribes'));
    }
}
