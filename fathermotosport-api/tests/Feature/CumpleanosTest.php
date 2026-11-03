<?php

namespace Tests\Feature;

use App\Filament\Pages\CumpleanosPage;
use App\Mail\CorreoCumpleanos;
use App\Mail\CorreoPromocional;
use App\Models\BirthdayEmail;
use App\Models\BirthdaySetting;
use App\Models\Coupon;
use App\Models\EmailVerificationCode;
use App\Models\Role;
use App\Models\User;
use App\Services\CumpleanosPrueba;
use App\Services\EnvioPromocional;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Correos de cumpleaños (remarketing, fase 2): destinatario con evidencia, un correo por
 * persona y año, mismo cupón en los reintentos, 29 de febrero, recuperación de 3 días,
 * cupo, ajustes del panel e interruptor.
 */
class CumpleanosTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/La_Paz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
        config(['app.frontend_url' => 'https://web.test']);
        BirthdaySetting::actual()->update(['auto_send' => true]);
    }

    /** Las 10:00 de La Paz de ese día (cuando corre el comando). */
    private function irA(string $fecha): void
    {
        $this->travelTo(CarbonImmutable::parse("{$fecha} 10:00:00", self::ZONA));
    }

    private function cliente(string $email, array $datos = []): User
    {
        $user = (new User)->forceFill([
            'role_id' => Role::where('slug', $datos['rol'] ?? 'cliente')->firstOrFail()->id,
            'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $email,
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active',
            'email_verified_at' => now(), 'email_verified_via' => 'code',
            'birth_date' => '1990-03-10',
            ...array_diff_key($datos, ['rol' => true]),
        ]);
        $user->save();

        return $user;
    }

    private function correr(): void
    {
        $this->artisan('cumpleanos:enviar')->assertSuccessful();
    }

    private function enviadosA(string $email): int
    {
        return Mail::sent(CorreoCumpleanos::class, fn ($m) => $m->hasTo($email))->count();
    }

    // ── Quién lo recibe ─────────────────────────────────────────────────────

    public function test_solo_reciben_los_clientes_con_evidencia_de_su_correo(): void
    {
        $this->irA('2027-03-10');
        $this->cliente('codigo@gmail.com', ['email_verified_via' => null]);
        EmailVerificationCode::create(['email' => 'Codigo@Gmail.com', 'code' => '123456', 'expires_at' => now(), 'used_at' => now()]);
        $this->cliente('google-viejo@gmail.com', ['email_verified_via' => null, 'avatar' => 'https://lh3.googleusercontent.com/a/x']);
        $this->cliente('google@gmail.com', ['email_verified_via' => 'google']);
        $this->cliente('paypal@gmail.com', ['email_verified_via' => 'paypal']);
        $this->cliente('admin@gmail.com', ['email_verified_via' => 'admin']);
        $this->cliente('sinrastro@gmail.com', ['email_verified_via' => null]);
        $this->cliente('reset@gmail.com', ['email_verified_via' => 'password_reset']);
        $this->cliente('sinverificar@gmail.com', ['email_verified_at' => null, 'email_verified_via' => null]);

        $this->correr();

        foreach (['codigo@gmail.com', 'google-viejo@gmail.com', 'google@gmail.com', 'paypal@gmail.com', 'admin@gmail.com'] as $email) {
            $this->assertSame(1, $this->enviadosA($email), $email);
        }
        foreach (['sinrastro@gmail.com', 'reset@gmail.com', 'sinverificar@gmail.com'] as $email) {
            $this->assertSame(0, $this->enviadosA($email), $email);
        }
        // Los excluidos no gastan cupo.
        $this->assertSame(5, app(EnvioPromocional::class)->enviadosHoy());
    }

    public function test_sin_fecha_de_nacimiento_baja_inactivo_o_borrado_no_reciben(): void
    {
        $this->irA('2027-03-10');
        $this->cliente('sinfecha@gmail.com', ['birth_date' => null]);
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $this->cliente('inactivo@gmail.com', ['status' => 'inactive']);
        $this->cliente('borrado@gmail.com')->delete();
        $this->cliente('otrodia@gmail.com', ['birth_date' => '1990-03-11']);

        $this->correr();

        Mail::assertNotSent(CorreoCumpleanos::class);
        $this->assertSame(0, BirthdayEmail::count());
        $this->assertSame(0, Coupon::where('code', 'like', 'BDAY-%')->count());
    }

    // ── Sin duplicados y mismo cupón ────────────────────────────────────────

    public function test_dos_ejecuciones_el_mismo_dia_no_duplican(): void
    {
        $this->irA('2027-03-10');
        $user = $this->cliente('ana@gmail.com');

        $this->correr();
        $this->travel(3)->hours();
        $this->correr();

        $this->assertSame(1, $this->enviadosA('ana@gmail.com'));
        $this->assertSame(1, BirthdayEmail::where('user_id', $user->id)->count());
        $this->assertSame(1, Coupon::where('user_id', $user->id)->where('code', 'like', 'BDAY-%')->count());

        // Al día siguiente (recuperación de 3 días) tampoco se repite.
        $this->irA('2027-03-11');
        $this->correr();
        $this->assertSame(1, $this->enviadosA('ana@gmail.com'));
    }

    public function test_un_fallo_de_envio_reintenta_con_el_mismo_cupon(): void
    {
        $this->irA('2027-03-10');
        $user = $this->cliente('ana@gmail.com');
        $this->app->instance(EnvioPromocional::class, new class extends EnvioPromocional
        {
            public int $llamadas = 0;

            public function enviar(CorreoPromocional $correo): array
            {
                return ++$this->llamadas === 1 ? ['estado' => 'fallido', 'motivo' => 'error al enviar'] : parent::enviar($correo);
            }
        });

        $this->correr();
        $fila = BirthdayEmail::where('user_id', $user->id)->sole();
        $this->assertSame(BirthdayEmail::FALLIDO, $fila->status);
        $this->assertSame(1, $fila->attempts);
        $codigo = $fila->coupon->code;

        $this->irA('2027-03-11');
        $this->correr();

        $fila->refresh();
        $this->assertSame(BirthdayEmail::ENVIADO, $fila->status);
        $this->assertSame($codigo, $fila->coupon->code);
        $this->assertSame(1, Coupon::where('user_id', $user->id)->where('code', 'like', 'BDAY-%')->count());
        Mail::assertSent(CorreoCumpleanos::class, fn ($m) => $m->codigo === $codigo);
    }

    // ── 29 de febrero y recuperación ────────────────────────────────────────

    public function test_quien_nacio_el_29_de_febrero_lo_recibe_el_28_en_anios_no_bisiestos(): void
    {
        $this->cliente('bisiesto@gmail.com', ['birth_date' => '2000-02-29']);
        $this->cliente('veintiocho@gmail.com', ['birth_date' => '1995-02-28']);

        $this->irA('2027-02-28'); // 2027 no es bisiesto
        $this->correr();
        $this->assertSame(1, $this->enviadosA('bisiesto@gmail.com'));
        $this->assertSame(1, $this->enviadosA('veintiocho@gmail.com'));
        $this->assertSame('2027-02-28', BirthdayEmail::where('year', 2027)->first()->birthday_date->toDateString());
    }

    public function test_en_anio_bisiesto_lo_recibe_el_29(): void
    {
        $this->cliente('bisiesto@gmail.com', ['birth_date' => '2000-02-29']);

        $this->irA('2028-02-28'); // 2028 sí es bisiesto: el 28 no le toca
        $this->correr();
        $this->assertSame(0, $this->enviadosA('bisiesto@gmail.com'));

        $this->irA('2028-02-29');
        $this->correr();
        $this->assertSame(1, $this->enviadosA('bisiesto@gmail.com'));
    }

    public function test_recupera_los_ultimos_3_dias_si_el_servidor_estuvo_caido(): void
    {
        $this->cliente('anteayer@gmail.com', ['birth_date' => '1990-03-08']);
        $this->cliente('hace3dias@gmail.com', ['birth_date' => '1990-03-07']);

        $this->irA('2027-03-10');
        $this->correr();

        $this->assertSame(1, $this->enviadosA('anteayer@gmail.com'));
        $this->assertSame(0, $this->enviadosA('hace3dias@gmail.com'));
        $this->assertSame('2027-03-08', BirthdayEmail::sole()->birthday_date->toDateString());
    }

    // ── Cupón ───────────────────────────────────────────────────────────────

    public function test_el_cupon_es_personal_de_un_uso_y_vence_al_final_del_dia_en_la_paz(): void
    {
        $this->irA('2027-03-10');
        $user = $this->cliente('ana@gmail.com');

        $this->correr();

        $cupon = BirthdayEmail::sole()->coupon;
        $this->assertMatchesRegularExpression('/^BDAY-[A-Z0-9]{6}$/', $cupon->code);
        $this->assertSame($user->id, $cupon->user_id);
        $this->assertSame(1, $cupon->max_uses);
        $this->assertSame('percentage', $cupon->type);
        $this->assertEquals(5, (float) $cupon->value);
        $this->assertSame('2027-03-17 23:59:59', $cupon->expires_at->setTimezone(self::ZONA)->format('Y-m-d H:i:s'));

        // Solo su dueña con sesión; y una sola vez.
        $this->postJson('/api/v1/coupons/validate', ['code' => $cupon->code, 'amount' => 100])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/coupons/validate', ['code' => $cupon->code, 'amount' => 100])
            ->assertOk()->assertJsonPath('discount', 5);
        $cupon->update(['used_count' => 1]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/coupons/validate', ['code' => $cupon->code, 'amount' => 100])->assertStatus(422);
    }

    // ── Ajustes del panel ───────────────────────────────────────────────────

    public function test_los_ajustes_del_panel_cambian_porcentaje_y_dias(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        Livewire::test(CumpleanosPage::class)
            ->set('data.discount_percent', 12.5)
            ->set('data.valid_days', 3)
            ->set('data.auto_send', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Ajustes de cumpleaños guardados');

        $this->irA('2027-03-10');
        $this->cliente('ana@gmail.com');
        $this->correr();

        $cupon = BirthdayEmail::sole()->coupon;
        $this->assertEquals(12.5, (float) $cupon->value);
        $this->assertSame('2027-03-13', $cupon->expires_at->setTimezone(self::ZONA)->toDateString());
    }

    public function test_el_panel_rechaza_valores_fuera_de_rango(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        foreach ([['discount_percent', 0.4], ['discount_percent', 90.5], ['discount_percent', 12.555], ['valid_days', 0], ['valid_days', 91]] as [$campo, $valor]) {
            Livewire::test(CumpleanosPage::class)->set("data.{$campo}", $valor)->call('save')->assertHasErrors(["data.{$campo}"]);
        }

        $this->assertEquals(5, (float) BirthdaySetting::actual()->discount_percent);
        $this->assertSame(7, BirthdaySetting::actual()->valid_days);
    }

    public function test_el_interruptor_viene_apagado_y_apagado_no_envia(): void
    {
        BirthdaySetting::query()->delete();
        $this->assertFalse(BirthdaySetting::actual()->auto_send, 'valor inicial: apagado');

        $this->irA('2027-03-10');
        $this->cliente('ana@gmail.com');
        $this->correr();

        Mail::assertNotSent(CorreoCumpleanos::class);
        $this->assertSame(0, BirthdayEmail::count());
    }

    public function test_solo_el_administrador_entra_a_la_pagina(): void
    {
        $empleado = $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado']);

        $this->assertFalse(CumpleanosPage::canAccess(), 'sin sesión');
        $this->actingAs($empleado);
        $this->assertFalse(CumpleanosPage::canAccess(), 'empleado');
        Livewire::test(CumpleanosPage::class)->assertForbidden();

        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $this->assertTrue(CumpleanosPage::canAccess());
        $this->get('/admin/cumpleanos')->assertOk();
    }

    // ── Cupo ────────────────────────────────────────────────────────────────

    public function test_el_cupo_diario_pospone_al_dia_siguiente_sin_perder_ni_duplicar(): void
    {
        config(['marketing.limits.per_day' => 2]);
        $this->irA('2027-03-10');
        foreach (['a', 'b', 'c'] as $letra) {
            $this->cliente("{$letra}@gmail.com");
        }

        $this->correr();
        $this->assertSame(2, Mail::sent(CorreoCumpleanos::class)->count());
        $pendiente = BirthdayEmail::where('status', BirthdayEmail::PENDIENTE)->sole();
        $codigo = $pendiente->coupon->code;

        $this->irA('2027-03-11');
        $this->correr();

        $this->assertSame(3, Mail::sent(CorreoCumpleanos::class)->count());
        $this->assertSame(3, BirthdayEmail::where('status', BirthdayEmail::ENVIADO)->count());
        $this->assertSame($codigo, $pendiente->fresh()->coupon->code, 'mismo cupón');
        foreach (['a', 'b', 'c'] as $letra) {
            $this->assertSame(1, $this->enviadosA("{$letra}@gmail.com"));
        }
        // Salió un día después: el plazo cuenta desde ese día.
        $this->assertSame('2027-03-18', $pendiente->fresh()->coupon->expires_at->setTimezone(self::ZONA)->toDateString());
    }

    // ── Contenido del correo ────────────────────────────────────────────────

    public function test_el_correo_sale_en_el_idioma_del_cliente_con_boton_codigo_y_vencimiento(): void
    {
        $vence = CarbonImmutable::parse('2027-03-17 23:59:59', self::ZONA);
        $casos = [
            'es' => ['¡Feliz cumpleaños, Ana!', '5 %', 'Válido hasta el 17 de marzo de 2027.', 'Quiero reclamar mi descuento', 'https://web.test/cart?cupon=BDAY-ABC123'],
            'pt' => ['Feliz aniversário, Ana!', '5%', 'Válido até 17 de março de 2027.', 'Quero resgatar meu desconto', 'https://web.test/pt/cart?cupon=BDAY-ABC123'],
            'en' => ['Happy birthday, Ana!', '5%', 'Valid until March 17, 2027.', 'Claim my discount', 'https://web.test/en/cart?cupon=BDAY-ABC123'],
            null => ['¡Feliz cumpleaños, Ana!', '5 %', 'Válido hasta el 17 de marzo de 2027.', 'Quiero reclamar mi descuento', 'https://web.test/cart?cupon=BDAY-ABC123'],
        ];

        foreach ($casos as $idioma => [$saludo, $porcentaje, $fecha, $boton, $url]) {
            $user = $this->cliente("cliente-{$idioma}@gmail.com", ['locale' => $idioma ?: null]);
            $correo = new CorreoCumpleanos($user, 'BDAY-ABC123', 5, $vence, 2027);
            $html = html_entity_decode($correo->render(), ENT_QUOTES);

            foreach ([$saludo, $porcentaje, $fecha, $boton, 'BDAY-ABC123', $url, __('marketing.footer_reason', [], $idioma ?: 'es')] as $esperado) {
                $this->assertStringContainsString($esperado, $html, "{$idioma}: {$esperado}");
            }
            // Sin hora ni zona en el vencimiento, y sin enlace de baja visible en el pie.
            foreach (['23:59', '11:59', 'Bolivia', 'Bolívia', 'La Paz'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $html, "{$idioma}: {$prohibido}");
            }
            $this->assertStringNotContainsString('/baja?u=', $html, (string) $idioma);
            $this->assertStringNotContainsString(__('marketing.unsubscribe', [], $idioma ?: 'es'), $html, (string) $idioma);

            // Las cabeceras de baja de un clic siguen (no se ven, ayudan con Gmail).
            $cabeceras = $correo->headers()->text;
            $this->assertStringContainsString('/api/v1/promos/unsubscribe?u='.$user->id, $cabeceras['List-Unsubscribe']);
            $this->assertSame('List-Unsubscribe=One-Click', $cabeceras['List-Unsubscribe-Post']);
        }
    }

    // ── Saludo con el primer nombre ─────────────────────────────────────────

    public function test_saluda_con_el_primer_nombre_o_sin_nombre(): void
    {
        $saludos = [
            'es' => ['¡Feliz cumpleaños, %s!', '¡Feliz cumpleaños!', '¡Feliz cumpleaños, %s! Tienes un regalo de FatherMotoSport', '¡Feliz cumpleaños! Tienes un regalo de FatherMotoSport'],
            'pt' => ['Feliz aniversário, %s!', 'Feliz aniversário!', 'Feliz aniversário, %s! Você tem um presente da FatherMotoSport', 'Feliz aniversário! Você tem um presente da FatherMotoSport'],
            'en' => ['Happy birthday, %s!', 'Happy birthday!', 'Happy birthday, %s! You have a gift from FatherMotoSport', 'Happy birthday! You have a gift from FatherMotoSport'],
        ];
        $nombres = [
            'gerson rejas' => 'Gerson',             // nombre real: solo el primero
            '  GERSON   rejas ' => 'Gerson',        // espacios de más y mayúsculas
            'ana-maría lópez' => 'Ana-María',      // compuesto con guion y tildes
            'josé luis' => 'José',
            'ÑANDÚ' => 'Ñandú',
            "o'brien" => "O'Brien",
            '¡gerson!' => 'Gerson',                 // puntuación en los bordes
            '' => null,
            '   ' => null,
            'Cliente' => null,
            'CLIENTE perez' => null,
            'gerson@gmail.com' => null,
            '12345' => null,
            'A' => null,
            'g3rson' => null,
        ];
        $vence = CarbonImmutable::parse('2027-03-17 23:59:59', self::ZONA);

        foreach ($saludos as $idioma => [$con, $sin, $asuntoCon, $asuntoSin]) {
            foreach ($nombres as $nombre => $esperado) {
                $user = (new User)->forceFill(['id' => (string) Str::uuid(), 'first_name' => $nombre, 'last_name' => 'Rejas', 'email' => 'x@gmail.com', 'locale' => $idioma]);
                $correo = new CorreoCumpleanos($user, 'BDAY-ABC123', 5, $vence, 2027);
                $html = html_entity_decode($correo->render(), ENT_QUOTES);
                $caso = "{$idioma} «{$nombre}»";

                $this->assertSame($esperado, $correo->nombre(), $caso);
                $this->assertStringContainsString($esperado ? sprintf($con, $esperado) : $sin, $html, $caso);
                $this->assertSame($esperado ? sprintf($asuntoCon, $esperado) : $asuntoSin, $correo->envelope()->subject, $caso);
                // Nunca "Cliente" ni una coma suelta antes del signo.
                $this->assertStringNotContainsString('Cliente', strip_tags($html), $caso);
                $this->assertDoesNotMatchRegularExpression('/,\s*!/u', strip_tags($html), $caso);
            }
        }
    }

    public function test_el_envio_real_saluda_con_el_nombre_de_la_cuenta(): void
    {
        $this->irA('2027-03-10');
        $this->cliente('gerson@gmail.com', ['first_name' => 'gerson rejas', 'last_name' => '']);
        $this->cliente('sin-nombre@gmail.com', ['first_name' => 'Cliente', 'last_name' => '']);

        $this->correr();

        $gerson = Mail::sent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('gerson@gmail.com'))->first();
        $this->assertSame('¡Feliz cumpleaños, Gerson! Tienes un regalo de FatherMotoSport', $gerson->envelope()->subject);
        $this->assertStringContainsString('¡Feliz cumpleaños, Gerson!', html_entity_decode($gerson->render(), ENT_QUOTES));

        $otro = Mail::sent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('sin-nombre@gmail.com'))->first();
        $this->assertSame('¡Feliz cumpleaños! Tienes un regalo de FatherMotoSport', $otro->envelope()->subject);
    }

    public function test_la_prueba_saluda_sin_nombre_salvo_con_la_opcion_nombre(): void
    {
        $this->artisan('cumpleanos:prueba', ['email' => 'a@gmail.com'])->expectsOutputToContain('Saludo: sin nombre')->assertSuccessful();
        $this->artisan('cumpleanos:prueba', ['email' => 'b@gmail.com', '--nombre' => 'gerson rejas', '--idioma' => 'en'])->expectsOutputToContain('Saludo: Gerson')->assertSuccessful();

        $sin = Mail::sent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('a@gmail.com'))->first();
        $this->assertNull($sin->nombre());
        $this->assertStringContainsString('¡Feliz cumpleaños!', html_entity_decode($sin->render(), ENT_QUOTES));
        $this->assertStringNotContainsString('Cliente', strip_tags($sin->render()));

        $con = Mail::sent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('b@gmail.com'))->first();
        $this->assertSame('Happy birthday, Gerson! You have a gift from FatherMotoSport', $con->envelope()->subject);
        $this->assertSame(CumpleanosPrueba::CODIGO, $con->codigo);
    }

    public function test_el_panel_no_menciona_la_zona(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        Livewire::test(CumpleanosPage::class)
            ->assertSee('Enviar automáticamente cada día a las 10:00')
            ->assertSee('Vence al final de ese día.')
            ->assertDontSee('La Paz');
    }

    // ── Vista previa, prueba y simulación ───────────────────────────────────

    public function test_la_vista_previa_y_el_envio_de_prueba_no_crean_cupones(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        $this->assertStringContainsString('BDAY-PRUEBA', app(CumpleanosPrueba::class)->html('pt'));

        Livewire::test(CumpleanosPage::class)
            ->mountAction('vista_previa_es')
            ->assertSee('BDAY-PRUEBA');

        Livewire::test(CumpleanosPage::class)
            ->callAction('enviar_prueba', ['email' => 'probador@gmail.com', 'idioma' => 'en'])
            ->assertNotified('Prueba enviada a probador@gmail.com');

        Mail::assertSent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('probador@gmail.com') && $m->codigo === 'BDAY-PRUEBA' && $m->idioma() === 'en');
        $this->artisan('cumpleanos:prueba', ['email' => 'otro@gmail.com', '--idioma' => 'pt'])->assertSuccessful();
        Mail::assertSent(CorreoCumpleanos::class, fn ($m) => $m->hasTo('otro@gmail.com'));

        $this->assertSame(0, Coupon::where('code', 'like', 'BDAY-%')->count());
        $this->assertSame(0, BirthdayEmail::count());
    }

    public function test_simular_lista_sin_enviar_ni_crear_nada(): void
    {
        $this->cliente('ana.lopez@gmail.com');
        $this->cliente('sinrastro@gmail.com', ['email_verified_via' => null]);

        $this->artisan('cumpleanos:simular', ['fecha' => '2027-03-10'])
            ->expectsOutputToContain('1 recibirían el correo')
            ->expectsOutputToContain('an•••••••@gmail.com')
            ->expectsOutputToContain('por falta de evidencia de verificación: 1')
            ->doesntExpectOutputToContain('ana.lopez@gmail.com')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, BirthdayEmail::count());
        $this->assertSame(0, Coupon::where('code', 'like', 'BDAY-%')->count());
    }
}
