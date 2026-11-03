<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use App\Filament\Resources\CampaignResource\Pages\EditCampaign;
use App\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use App\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use App\Filament\Resources\MailCampaignResource;
use App\Filament\Resources\MailCampaignResource\Pages\ListMailCampaigns;
use App\Filament\Resources\MailCampaignResource\Pages\ViewMailCampaign;
use App\Filament\Resources\MailCampaignResource\RelationManagers\DestinatariosRelationManager;
use App\Mail\CorreoCampana;
use App\Mail\CorreoPromocional;
use App\Models\Category;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\AvisoDescuento;
use App\Services\CampanaManual;
use App\Services\EnvioPromocional;
use App\Support\ImagenDeCampana;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Campañas manuales de correo (remarketing, fase 4): borrador, programación, envío por
 * tandas con cupo, pausa, cancelación, duplicado, segmentos, destinatarios fiables,
 * contenido limpio, imagen re-codificada, permisos e historial con el correo completo.
 */
class CampanaManualTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/La_Paz';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
        config([
            'app.frontend_url' => 'https://web.test',
            'app.url' => 'https://api.test',
            'filesystems.disks.public.url' => 'https://api.test/storage',
            'marketing.limits.per_minute' => 60,
            'marketing.limits.per_day' => 100,
        ]);
        $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', self::ZONA));
        $this->admin = User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    private function cliente(string $email, array $datos = []): User
    {
        $user = (new User)->forceFill([
            'role_id' => Role::where('slug', $datos['rol'] ?? 'cliente')->firstOrFail()->id,
            'first_name' => 'Ana', 'last_name' => 'Prueba', 'email' => $email,
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active',
            'email_verified_at' => now(), 'email_verified_via' => 'code',
            'country' => 'Bolivia', 'locale' => 'es',
            ...array_diff_key($datos, ['rol' => true]),
        ]);
        $user->save();

        return $user;
    }

    private function contenido(string $idioma, string $marca): array
    {
        return [
            'asunto' => "Asunto {$marca}", 'preheader' => "Preheader {$marca}", 'titular' => "Titular {$marca}",
            'cuerpo' => "<p>Texto {$marca} con <strong>negrita</strong> y <a href=\"https://web.test/catalog\">enlace</a>.</p>",
            'boton_texto' => "Botón {$marca}", 'boton_url' => 'https://web.test/catalog', 'imagen_alt' => "Alt {$marca}",
        ];
    }

    private function payload(array $extra = []): array
    {
        return [
            'contenido' => ['es' => $this->contenido('es', 'ES'), 'pt' => $this->contenido('pt', 'PT'), 'en' => $this->contenido('en', 'EN')],
            'imagen' => null,
            'segmento' => [],
            ...$extra,
        ];
    }

    private function campana(array $datos = []): MailCampaign
    {
        return MailCampaign::create([
            'type' => MailCampaign::TIPO_MANUAL, 'name' => 'Cascos 2027', 'dedupe_key' => 'manual:'.Str::uuid(),
            'payload' => $this->payload(), 'status' => MailCampaign::BORRADOR, ...$datos,
        ]);
    }

    private function servicio(): CampanaManual
    {
        return app(CampanaManual::class);
    }

    private function tanda(): void
    {
        $this->artisan('campanas:enviar')->assertSuccessful();
    }

    private function ver(MailCampaign $campana, ?User $quien = null)
    {
        $this->actingAs($quien ?? $this->admin);

        return Livewire::test(ViewCampaign::class, ['record' => $campana->getRouteKey()]);
    }

    private function correosA(string $email): int
    {
        return Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo($email))->count();
    }

    // ── Crear, programar, enviar ────────────────────────────────────────────

    public function test_el_admin_crea_un_borrador_limpio_y_un_doble_clic_no_lo_duplica(): void
    {
        $this->actingAs($this->admin);
        $pagina = Livewire::test(CreateCampaign::class)
            ->fillForm([
                'form_token' => 'token-fijo',
                'name' => 'Cascos 2027',
                'payload' => ['contenido' => ['es' => [
                    ...$this->contenido('es', 'ES'),
                    'cuerpo' => '<div>Hola <script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">mal</a> <a href="https://web.test">bien</a></div>',
                ]]],
            ]);
        $pagina->call('create')->assertHasNoFormErrors();
        $pagina->call('create');

        $campana = MailCampaign::sole();
        $this->assertSame([MailCampaign::TIPO_MANUAL, MailCampaign::BORRADOR, 'manual:token-fijo'], [$campana->type, $campana->status, $campana->dedupe_key]);
        $this->assertSame('<p>Hola mal <a href="https://web.test">bien</a></p>', $campana->payload['contenido']['es']['cuerpo']);
        Mail::assertNothingSent();
    }

    public function test_enviar_pide_confirmacion_y_envia_por_tandas_con_baja_y_cabeceras(): void
    {
        $ana = $this->cliente('ana@gmail.com');
        $this->cliente('beto@gmail.com', ['locale' => 'pt']);
        $campana = $this->campana();

        $this->ver($campana)->mountAction('enviar')->assertSee('Voy a enviar este correo a 2 clientes')
            ->assertSee('fuera por baja')->assertDontSee('rebote o queja');
        $this->ver($campana)->callAction('enviar', ['confirmo' => false])->assertHasActionErrors(['confirmo']);
        $this->assertSame(MailCampaign::BORRADOR, $campana->fresh()->status);

        $this->ver($campana)->callAction('enviar', ['confirmo' => true])->assertNotified('Envío iniciado');
        $this->assertSame([MailCampaign::ENVIANDO, 2], [$campana->fresh()->status, $campana->fresh()->total_count]);
        Mail::assertNothingSent();

        $this->tanda();
        $this->assertSame(MailCampaign::COMPLETADA, $campana->fresh()->status);
        $correo = Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo('ana@gmail.com'))->sole();
        $html = html_entity_decode($correo->render(), ENT_QUOTES);
        $this->assertSame('Asunto ES', $correo->envelope()->subject);
        $this->assertStringContainsString('/baja?u='.$ana->id, $html);
        $this->assertStringContainsString('Darte de baja de las promociones', $html);
        $cabeceras = $correo->headers()->text;
        $this->assertSame("campaign:{$campana->id}:{$ana->id}", $cabeceras['Resend-Idempotency-Key']);
        $this->assertStringContainsString('/api/v1/promos/unsubscribe?u='.$ana->id, $cabeceras['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $cabeceras['List-Unsubscribe-Post']);
        $this->assertFalse($correo->hasMetadata('referencia', "campaign_{$campana->id}"), 'sin etiqueta: ya no hay webhook que la lea');
        $this->assertSame('Asunto ES', Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo('beto@gmail.com'))->sole()->envelope()->subject, 'todo en español');
    }

    public function test_programada_sale_al_llegar_su_hora(): void
    {
        $this->cliente('ana@gmail.com');
        $campana = $this->campana(['scheduled_at' => now()->addHours(2)]);

        $this->ver($campana)->callAction('enviar', ['confirmo' => true])->assertNotified('Campaña programada');
        $this->assertSame([MailCampaign::PROGRAMADA, 0], [$campana->fresh()->status, $campana->recipients()->count()]);

        $this->tanda();
        $this->assertSame(0, $this->correosA('ana@gmail.com'), 'antes de la hora no sale');

        $this->travel(2)->hours();
        $this->tanda();
        $this->assertSame([MailCampaign::COMPLETADA, 1], [$campana->fresh()->status, $this->correosA('ana@gmail.com')]);
    }

    public function test_si_el_servidor_estuvo_caido_sale_en_la_primera_pasada(): void
    {
        $this->cliente('ana@gmail.com');
        $campana = $this->campana(['status' => MailCampaign::PROGRAMADA, 'scheduled_at' => now()->subDays(2)]);

        $this->tanda();

        $this->assertSame([MailCampaign::COMPLETADA, 1], [$campana->fresh()->status, $this->correosA('ana@gmail.com')]);
    }

    public function test_pausar_reanudar_y_cancelar_sin_perder_lo_pendiente(): void
    {
        config(['marketing.limits.per_minute' => 1]);
        foreach (['a', 'b', 'c', 'd'] as $l) {
            $this->cliente("{$l}@gmail.com");
        }
        $campana = $this->servicio()->programar($this->campana());

        $this->tanda();
        $this->assertSame(1, $campana->fresh()->sent_count);

        $this->ver($campana)->callAction('pausar')->assertNotified('Campaña pausada');
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame([MailCampaign::PAUSADA, 1, 3], [$campana->fresh()->status, $campana->fresh()->sent_count, $campana->recipients()->where('status', MailCampaignRecipient::PENDIENTE)->count()]);

        $this->ver($campana)->callAction('reanudar');
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame(2, $campana->fresh()->sent_count);

        $this->ver($campana)->callAction('cancelar')->assertNotified('Campaña cancelada');
        $this->travel(1)->minutes();
        $this->tanda();
        $campana->refresh();
        $this->assertSame([MailCampaign::CANCELADA, 2, 2], [$campana->status, $campana->sent_count, $campana->recipients()->where('status', MailCampaignRecipient::CANCELADO)->count()]);
        Mail::assertSent(CorreoCampana::class, 2);
    }

    public function test_una_campana_enviada_no_se_edita_y_se_duplica_como_borrador(): void
    {
        $this->cliente('ana@gmail.com');
        $campana = $this->servicio()->programar($this->campana());
        $this->tanda();

        $this->actingAs($this->admin);
        $this->assertFalse(CampaignResource::canEdit($campana->fresh()));
        $this->get(CampaignResource::getUrl('edit', ['record' => $campana]))->assertForbidden();
        $this->ver($campana)->assertActionHidden('edit')->assertActionHidden('enviar');

        $this->ver($campana)->callAction('duplicar');
        $copia = MailCampaign::latest('id')->first();
        $this->assertNotSame($campana->id, $copia->id);
        $this->assertSame([MailCampaign::BORRADOR, 'Copia de Cascos 2027', $campana->payload], [$copia->status, $copia->name, $copia->payload]);
        $this->assertTrue(CampaignResource::canEdit($copia));
        $this->assertSame(0, $copia->recipients()->count());
    }

    public function test_doble_programacion_no_duplica_destinatarios(): void
    {
        $this->cliente('ana@gmail.com');
        $campana = $this->campana();
        $this->servicio()->programar($campana);

        $this->expectException(RuntimeException::class);
        try {
            $this->servicio()->programar($campana->fresh()->forceFill(['status' => MailCampaign::BORRADOR]));
        } finally {
            $this->assertSame(1, $campana->recipients()->count());
        }
    }

    // ── Segmentos y destinatarios ───────────────────────────────────────────

    public function test_segmento_por_pais_idioma_y_compradores(): void
    {
        $bo = $this->cliente('bo@gmail.com', ['country' => 'Bolivia', 'locale' => 'es']);
        $this->cliente('br@gmail.com', ['country' => 'Brasil', 'locale' => 'pt']);
        $sinIdioma = $this->cliente('sinidioma@gmail.com', ['country' => 'Bolivia', 'locale' => null]);
        $this->cliente('en@gmail.com', ['country' => 'Bolivia', 'locale' => 'en']);
        Order::create(['user_id' => $bo->id, 'status' => 'processing', 'payment_status' => 'paid', 'shipping_status' => 'pending', 'payment_method' => 'paypal', 'country' => 'Bolivia', 'total' => 100]);
        Order::create(['user_id' => $sinIdioma->id, 'status' => 'pending', 'payment_status' => 'pending', 'shipping_status' => 'pending', 'payment_method' => 'paypal', 'country' => 'Bolivia', 'total' => 100]);

        $correos = fn (array $segmento) => $this->servicio()->destinatarios($this->campana(['payload' => $this->payload(['segmento' => $segmento])]))
            ->pluck('email')->sort()->values()->all();

        $this->assertSame(['bo@gmail.com', 'en@gmail.com', 'sinidioma@gmail.com'], $correos(['paises' => ['Bolivia']]));
        $this->assertSame(['bo@gmail.com', 'sinidioma@gmail.com'], $correos(['paises' => ['Bolivia'], 'idiomas' => ['es']]), 'es incluye sin idioma');
        $this->assertSame(['br@gmail.com', 'en@gmail.com'], $correos(['idiomas' => ['pt', 'en']]));
        $this->assertSame(['bo@gmail.com'], $correos(['solo_compradores' => true]), 'solo con pedido pagado');
        $this->assertCount(4, $correos([]));
    }

    public function test_solo_destinatarios_fiables_y_el_resumen_cuenta_los_excluidos(): void
    {
        $this->cliente('ok@gmail.com');
        $this->cliente('sinevidencia@gmail.com', ['email_verified_via' => null]);
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $this->cliente('rebote@gmail.com', ['email_bounced_at' => now()]);
        $this->cliente('queja@gmail.com', ['email_complained_at' => now()]);
        $this->cliente('inactivo@gmail.com', ['status' => 'inactive']);
        $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado']);
        $campana = $this->campana();

        $this->assertSame(['ok@gmail.com'], $this->servicio()->destinatarios($campana)->pluck('email')->all());
        $resumen = $this->servicio()->resumen($campana);
        // Rebote y queja marcados a mano siguen fuera, pero el resumen ya no los cuenta aparte.
        $this->assertSame([1, 1, 1, 1], [$resumen['recibirian'], $resumen['sin_evidencia'], $resumen['baja'], $resumen['caben_hoy']]);
        $this->assertArrayNotHasKey('rebote_o_queja', $resumen);
    }

    public function test_una_baja_o_un_rebote_entre_la_creacion_y_el_envio_lo_excluye(): void
    {
        $antes = $this->cliente('antes@gmail.com');
        $durante = $this->cliente('durante@gmail.com');
        $this->cliente('bien@gmail.com');
        $campana = $this->campana(['scheduled_at' => now()->addHour()]);
        $this->servicio()->programar($campana);

        $antes->forceFill(['promo_unsubscribed_at' => now()])->save();   // antes de empezar: no entra
        $this->travel(1)->hours();
        $this->servicio()->iniciarProgramadas();
        $durante->forceFill(['email_bounced_at' => now()])->save();      // ya en la lista: se omite al enviar
        $this->tanda();

        $this->assertSame(0, $campana->recipients()->where('email', 'antes@gmail.com')->count());
        $durante = $campana->recipients()->where('email', 'durante@gmail.com')->sole();
        $this->assertSame([MailCampaignRecipient::OMITIDO, 'omitido: rebote'], [$durante->status, $durante->reason]);
        $this->assertSame([0, 0, 1], [$this->correosA('antes@gmail.com'), $this->correosA('durante@gmail.com'), $this->correosA('bien@gmail.com')]);
    }

    // ── Cupo, idempotencia y fallos ─────────────────────────────────────────

    public function test_respeta_el_cupo_por_minuto_y_diario_y_sigue_al_dia_siguiente(): void
    {
        config(['marketing.limits.per_minute' => 2, 'marketing.limits.per_day' => 3]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $l) {
            $this->cliente("{$l}@gmail.com");
        }
        $campana = $this->servicio()->programar($this->campana());

        $this->tanda();
        $this->assertSame(2, $campana->fresh()->sent_count, 'por minuto');
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame(3, $campana->fresh()->sent_count, 'tope diario');
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame([3, MailCampaign::ENVIANDO], [$campana->fresh()->sent_count, $campana->fresh()->status]);

        $this->travelTo(CarbonImmutable::parse('2027-03-11 09:00:00', self::ZONA));
        $this->tanda();
        $this->travel(1)->minutes();
        $this->tanda();
        $this->assertSame([5, MailCampaign::COMPLETADA], [$campana->fresh()->sent_count, $campana->fresh()->status]);
        Mail::assertSent(CorreoCampana::class, 5);
    }

    public function test_cada_destinatario_recibe_una_vez_aunque_una_tanda_quede_colgada(): void
    {
        $this->cliente('ana@gmail.com');
        $campana = $this->servicio()->programar($this->campana());
        $campana->recipients()->update(['status' => MailCampaignRecipient::ENVIANDO, 'claim_token' => (string) Str::uuid(), 'updated_at' => now()->subMinutes(15)]);

        $this->tanda();
        $this->tanda();

        $this->assertSame(1, $this->correosA('ana@gmail.com'));
        $this->assertSame(MailCampaignRecipient::ENVIADO, $campana->recipients()->sole()->status);
    }

    public function test_un_fallo_no_detiene_al_resto(): void
    {
        $this->cliente('falla@gmail.com');
        $this->cliente('bien@gmail.com');
        $campana = $this->servicio()->programar($this->campana());
        $this->app->instance(EnvioPromocional::class, new class extends EnvioPromocional
        {
            public function enviar(CorreoPromocional $correo): array
            {
                return $correo->destinatario->email === 'falla@gmail.com' ? ['estado' => 'fallido', 'motivo' => 'error al enviar'] : parent::enviar($correo);
            }
        });

        $this->tanda();

        $this->assertSame(1, $this->correosA('bien@gmail.com'));
        $falla = $campana->recipients()->where('email', 'falla@gmail.com')->sole();
        $this->assertSame([MailCampaignRecipient::PENDIENTE, 1, 'error al enviar'], [$falla->status, $falla->attempts, $falla->reason]);
    }

    // ── Contenido, vista previa, prueba e imagen ────────────────────────────

    public function test_todos_reciben_el_correo_en_espanol_aunque_su_idioma_sea_otro(): void
    {
        $pt = $this->cliente('pt@gmail.com', ['locale' => 'pt', 'country' => 'Brasil']);
        $en = $this->cliente('en@gmail.com', ['locale' => 'en', 'country' => 'Estados Unidos']);
        $payload = $this->payload();
        unset($payload['contenido']['pt'], $payload['contenido']['en']);
        $campana = $this->servicio()->programar($this->campana(['payload' => $payload]));

        $this->tanda();

        foreach ([$pt, $en] as $user) {
            $correo = Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo($user->email))->sole();
            $html = html_entity_decode($correo->render(), ENT_QUOTES);
            $this->assertSame('es', $correo->idioma());
            $this->assertSame('Asunto ES', $correo->envelope()->subject);
            foreach (['Titular ES', 'Recibes este correo porque tienes una cuenta en FatherMotoSport', 'Darte de baja de las promociones', 'Ver tienda', 'lang="es"', 'https://web.test/baja?u='.$user->id] as $texto) {
                $this->assertStringContainsString($texto, $html, "{$user->email}: {$texto}");
            }
            foreach (['Você recebe', 'You are receiving', 'Unsubscribe from', '/pt/baja', '/en/baja'] as $otro) {
                $this->assertStringNotContainsString($otro, $html, "{$user->email}: {$otro}");
            }
        }
        $this->assertSame([], CampanaManual::idiomasConEspanol($campana->payload), 'sin avisos de idiomas faltantes');
    }

    // ── Formulario simplificado y vista previa en vivo ───────────────────────

    public function test_el_formulario_es_solo_en_espanol_con_los_filtros_plegados(): void
    {
        $this->actingAs($this->admin);
        $pagina = Livewire::test(CreateCampaign::class);

        foreach (['asunto', 'preheader', 'titular', 'cuerpo', 'imagen_alt', 'boton_texto', 'boton_url'] as $campo) {
            $pagina->assertFormFieldExists("payload.contenido.es.{$campo}");
            $pagina->assertFormFieldDoesNotExist("payload.contenido.pt.{$campo}");
            $pagina->assertFormFieldDoesNotExist("payload.contenido.en.{$campo}");
        }
        $pagina->assertFormFieldExists('payload.segmento.paises')
            ->assertSee('Se envía a todos los clientes con correo comprobado que no se dieron de baja.')
            ->assertDontSee('rebote')->assertDontSee('queja')
            ->assertSee('Filtros avanzados (opcional)')
            ->assertSee('Sin filtros, se envía a todos.')
            ->assertSee('Contenido (en español)')
            ->assertDontSee('Opcional: si lo dejas vacío, estos clientes reciben el español.');
    }

    public function test_sin_filtros_va_a_todos_los_destinatarios_fiables(): void
    {
        $this->cliente('bo@gmail.com', ['country' => 'Bolivia', 'locale' => 'es']);
        $this->cliente('br@gmail.com', ['country' => 'Brasil', 'locale' => 'pt']);
        $this->cliente('sinpais@gmail.com', ['country' => null, 'locale' => null]);
        $this->cliente('baja@gmail.com', ['promo_unsubscribed_at' => now()]);
        $campana = $this->campana(['payload' => $this->payload(['segmento' => ['paises' => [], 'idiomas' => [], 'solo_compradores' => false]])]);

        $fiables = User::destinatariosDePromociones()->pluck('email')->sort()->values()->all();
        $this->assertSame(['bo@gmail.com', 'br@gmail.com', 'sinpais@gmail.com'], $fiables);
        $this->assertSame($fiables, $this->servicio()->destinatarios($campana)->pluck('email')->sort()->values()->all());
        $this->ver($campana)->mountAction('enviar')->assertSee('Voy a enviar este correo a 3 clientes');

        $this->servicio()->programar($campana);
        $this->tanda();
        Mail::assertSent(CorreoCampana::class, 3);
    }

    public function test_la_vista_previa_en_vivo_se_actualiza_sin_guardar_ni_contar_en_el_cupo(): void
    {
        $this->actingAs($this->admin);
        $pagina = Livewire::test(CreateCampaign::class)
            ->assertSee('Escribe el asunto, el titular y el texto para ver la vista previa.')
            ->fillForm(['payload' => ['contenido' => ['es' => ['asunto' => 'Asunto en vivo', 'preheader' => 'Preheader en vivo', 'titular' => 'Primer titular', 'cuerpo' => '<p>Texto en vivo</p>', 'boton_texto' => 'Ver la tienda', 'boton_url' => 'https://web.test/catalog']]]])
            ->assertSee('Asunto en vivo')->assertSee('Preheader en vivo')
            ->assertSee('Primer titular')->assertSee('Ver la tienda')
            ->assertSee('width:640px', false);

        $pagina->fillForm(['payload' => ['contenido' => ['es' => ['asunto' => 'Asunto en vivo', 'titular' => 'Titular cambiado', 'cuerpo' => '<p>Texto en vivo</p>']]]])
            ->assertSee('Titular cambiado')->assertDontSee('Primer titular')
            ->fillForm(['vista_ancho' => '375'])
            ->assertSee('width:375px', false);

        $this->assertSame(0, MailCampaign::count(), 'no se guarda');
        Mail::assertNothingSent();
        $this->assertSame(0, app(EnvioPromocional::class)->enviadosHoy());
    }

    public function test_la_prueba_desde_el_formulario_manda_lo_escrito_sin_guardar(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(CreateCampaign::class)
            ->fillForm(['payload' => ['contenido' => ['es' => ['asunto' => 'Sin guardar todavía', 'titular' => 'Titular de prueba', 'cuerpo' => '<p>Hola</p>']]]])
            ->callAction('enviar_prueba', ['email' => 'probador@gmail.com'])
            ->assertNotified('Prueba enviada a probador@gmail.com');

        $correo = Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo('probador@gmail.com'))->sole();
        $this->assertSame('Sin guardar todavía', $correo->envelope()->subject);
        $this->assertStringContainsString('Titular de prueba', $correo->render());
        $this->assertSame([0, 0, 0], [MailCampaign::count(), MailCampaignRecipient::count(), app(EnvioPromocional::class)->enviadosHoy()]);
    }

    public function test_editar_un_borrador_antiguo_conserva_su_contenido_en_pt_y_en(): void
    {
        $campana = $this->campana();
        $this->actingAs($this->admin);

        Livewire::test(EditCampaign::class, ['record' => $campana->getRouteKey()])
            ->fillForm(['payload' => ['contenido' => ['es' => [...$this->contenido('es', 'ES'), 'titular' => 'Titular editado']]]])
            ->call('save')->assertHasNoFormErrors();

        $payload = $campana->fresh()->payload;
        $this->assertSame('Titular editado', $payload['contenido']['es']['titular']);
        $this->assertSame('Titular PT', $payload['contenido']['pt']['titular']);
        $this->assertSame('Titular EN', $payload['contenido']['en']['titular']);
    }

    public function test_una_campana_antigua_en_tres_idiomas_se_sigue_enviando_en_espanol(): void
    {
        $this->cliente('pt@gmail.com', ['locale' => 'pt']);
        $campana = $this->campana();   // contenido es, pt y en, como las creadas antes
        $this->assertSame([], CampanaManual::errores($campana));

        $this->servicio()->programar($campana);
        $this->tanda();

        $correo = Mail::sent(CorreoCampana::class, fn ($m) => $m->hasTo('pt@gmail.com'))->sole();
        $this->assertSame('Asunto ES', $correo->envelope()->subject);
        $this->assertSame(MailCampaign::COMPLETADA, $campana->fresh()->status);
        $this->assertSame('Asunto PT', $campana->fresh()->payload['contenido']['pt']['asunto'], 'no se borra el contenido antiguo');
    }

    public function test_el_correo_lleva_el_contenido_limpio(): void
    {
        $payload = $this->payload();
        $payload['contenido']['es']['cuerpo'] = '<p onmouseover="x()">Hola <img src=x onerror="alert(1)"><a href="http://inseguro.com">http</a> <a href="https://bien.com" style="color:red">https</a></p><iframe src="https://e.com"></iframe><script>alert(1)</script>';
        $payload['contenido']['es']['boton_url'] = 'javascript:alert(1)';
        $html = $this->servicio()->correoDePrueba($this->campana(['payload' => $payload]), 'es')->render();

        foreach (['onmouseover', 'onerror', '<img src=x', '<iframe', '<script', 'javascript:', 'http://inseguro.com', 'style="color:red"'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html, $prohibido);
        }
        $this->assertStringContainsString('href="https://bien.com"', $html);
        $this->assertStringNotContainsString('Botón ES', $html, 'sin enlace https el botón no se muestra');
        $this->assertContains('El enlace del botón tiene que empezar con https:// (es).', CampanaManual::errores($this->campana(['payload' => $payload])));
    }

    public function test_la_imagen_se_valida_y_se_recodifica_con_url_https(): void
    {
        $foto = UploadedFile::fake()->image('Foto con espacios.jpg', 2400, 1200);
        $ruta = ImagenDeCampana::guardar($foto);

        try {
            $this->assertMatchesRegularExpression('#^campanas/[0-9a-f-]{36}\.jpg$#', $ruta);
            $guardada = Storage::disk('public')->path($ruta);
            [$ancho, $alto, $tipo] = getimagesize($guardada);
            $this->assertSame([1200, 600, IMAGETYPE_JPEG], [$ancho, $alto, $tipo], 'reducida a 1200 px y re-codificada');
            $this->assertNotSame(md5_file($foto->getRealPath()), md5_file($guardada));
            $this->assertSame('https://api.test/storage/'.$ruta, ImagenDeCampana::url($ruta));

            $campana = $this->campana(['payload' => $this->payload(['imagen' => $ruta])]);
            $html = $this->servicio()->correoDePrueba($campana, 'pt')->render();
            $this->assertStringContainsString('src="https://api.test/storage/'.$ruta.'" width="600" alt="Alt ES"', $html);
        } finally {
            Storage::disk('public')->delete($ruta);
        }

        // Un archivo que no es una imagen, aunque se llame .png, no pasa.
        $falsa = UploadedFile::fake()->createWithContent('foto.png', '<?php echo "hola"; ?>');
        $this->assertTrue(Validator::make(['f' => $falsa], ['f' => ImagenDeCampana::reglas()])->fails());
        $this->expectException(RuntimeException::class);
        ImagenDeCampana::guardar($falsa);
    }

    public function test_sin_alt_de_imagen_no_se_puede_enviar(): void
    {
        $payload = $this->payload(['imagen' => 'campanas/x.jpg']);
        $payload['contenido']['es']['imagen_alt'] = '';

        $this->assertContains('Falta el texto alternativo de la imagen (es).', CampanaManual::errores($this->campana(['payload' => $payload])));
    }

    public function test_el_envio_de_prueba_no_cuenta_ni_queda_como_destinatario(): void
    {
        $campana = $this->campana();

        $this->ver($campana)->callAction('enviar_prueba', ['email' => 'probador@gmail.com'])->assertNotified('Prueba enviada a probador@gmail.com');

        Mail::assertSent(CorreoCampana::class, fn ($m) => $m->hasTo('probador@gmail.com') && $m->envelope()->subject === 'Asunto ES');
        $this->assertSame(0, app(EnvioPromocional::class)->enviadosHoy());
        $this->assertSame([0, MailCampaign::BORRADOR], [MailCampaignRecipient::count(), $campana->fresh()->status]);
    }

    // ── Permisos e historial ────────────────────────────────────────────────

    public function test_el_empleado_ve_el_historial_pero_no_envia(): void
    {
        $empleado = $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado']);
        $campana = $this->campana();
        $this->actingAs($empleado);

        $this->assertTrue(CampaignResource::canAccess());
        $this->assertFalse(CampaignResource::canCreate());
        $this->assertFalse(CampaignResource::canEdit($campana));
        Livewire::test(ListCampaigns::class)->assertCanSeeTableRecords([$campana]);
        $this->ver($campana, $empleado)
            ->assertActionVisible('vista_previa')
            ->assertActionHidden('enviar')->assertActionHidden('enviar_prueba')->assertActionHidden('cancelar')
            ->assertActionHidden('duplicar')->assertActionHidden('edit');
    }

    public function test_el_historial_muestra_el_correo_completo_y_se_busca_por_correo_y_estado(): void
    {
        $this->cliente('juan.perez@gmail.com');
        $this->cliente('maria@hotmail.com');
        $campana = $this->servicio()->programar($this->campana());
        $juan = $campana->recipients()->where('email', 'juan.perez@gmail.com')->sole();
        $maria = $campana->recipients()->where('email', 'maria@hotmail.com')->sole();
        $maria->update(['status' => MailCampaignRecipient::OMITIDO, 'reason' => 'omitido: dado de baja']);

        foreach ([$this->admin, $this->cliente('empleado@fathermotosport.com', ['rol' => 'empleado'])] as $quien) {
            $this->actingAs($quien);
            Livewire::test(DestinatariosRelationManager::class, ['ownerRecord' => $campana, 'pageClass' => ViewCampaign::class])
                ->assertSee('juan.perez@gmail.com')->assertSee('maria@hotmail.com')->assertDontSee('j***')
                ->searchTable('juan.perez')
                ->assertCanSeeTableRecords([$juan])->assertCanNotSeeTableRecords([$maria]);

            Livewire::test(DestinatariosRelationManager::class, ['ownerRecord' => $campana, 'pageClass' => ViewCampaign::class])
                ->filterTable('status', MailCampaignRecipient::OMITIDO)
                ->assertCanSeeTableRecords([$maria])->assertCanNotSeeTableRecords([$juan])
                ->assertSee('Se dio de baja')->assertDontSee('omitido: dado de baja');
        }
    }

    public function test_el_historial_de_avisos_de_descuento_tambien_muestra_el_correo(): void
    {
        $aviso = MailCampaign::create(['type' => MailCampaign::TIPO_DESCUENTO, 'name' => 'Descuento en Cascos', 'payload' => [], 'status' => MailCampaign::COMPLETADA]);
        $fila = $aviso->recipients()->create(['email' => 'cliente.completo@gmail.com', 'status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now()]);
        $this->actingAs($this->admin);

        Livewire::test(DestinatariosRelationManager::class, ['ownerRecord' => $aviso, 'pageClass' => ViewMailCampaign::class])
            ->assertCanSeeTableRecords([$fila])->assertSee('cliente.completo@gmail.com');
        Livewire::test(ViewMailCampaign::class, ['record' => $aviso->getRouteKey()])->assertOk();
    }

    // ── Pantallas simplificadas ─────────────────────────────────────────────

    /** Campaña enviada con un enviado, un omitido por baja, un fallido y uno cancelado. */
    private function campanaConResultados(array $datos = []): MailCampaign
    {
        $campana = $this->campana(['status' => MailCampaign::COMPLETADA, 'created_by' => $this->admin->id, ...$datos]);
        $enviado = $this->cliente('enviado@gmail.com');
        $campana->recipients()->create(['user_id' => $enviado->id, 'email' => 'enviado@gmail.com', 'status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now()->subHour(), 'attempts' => 0]);
        $campana->recipients()->create(['email' => 'baja@gmail.com', 'status' => MailCampaignRecipient::OMITIDO, 'reason' => 'omitido: dado de baja']);
        $campana->recipients()->create(['email' => 'falla@gmail.com', 'status' => MailCampaignRecipient::FALLIDO, 'reason' => 'error al enviar', 'attempts' => 3]);
        $campana->recipients()->create(['email' => 'cancelado@gmail.com', 'status' => MailCampaignRecipient::CANCELADO, 'reason' => 'cancelado: cancelada desde el panel']);
        $enviado->forceFill(['promo_unsubscribed_at' => now()])->save();   // se dio de baja después de recibirla
        $campana->recontar();

        return $campana->fresh();
    }

    public function test_la_lista_tiene_solo_cinco_columnas_y_el_progreso_en_una(): void
    {
        $campana = $this->campanaConResultados();
        $this->actingAs($this->admin);

        $lista = Livewire::test(ListCampaigns::class)->assertCanSeeTableRecords([$campana]);
        foreach (['name', 'type', 'status', 'envio', 'progreso'] as $columna) {
            $lista->assertTableColumnExists($columna);
        }
        foreach (['sent_count', 'pending_count', 'failed_count', 'skipped_count', 'total_count', 'bajas', 'rebotes', 'quejas', 'cancel_reason', 'reason', 'scheduled_at', 'created_at'] as $retirada) {
            $lista->assertTableColumnDoesNotExist($retirada);
        }
        $lista->assertSee('1 de 4 enviados')->assertSee('Campaña')->assertSee('Enviada');
    }

    public function test_el_distintivo_de_fallidos_sale_solo_si_hay_fallidos(): void
    {
        $conFallidos = $this->campanaConResultados();
        $sinFallidos = $this->campana(['status' => MailCampaign::COMPLETADA, 'name' => 'Sin fallos']);
        $sinFallidos->recipients()->create(['email' => 'ok@gmail.com', 'status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now()]);
        $sinFallidos->recontar();

        $this->assertStringContainsString('1 fallido</span>', (string) MailCampaignResource::progresoConFallidos($conFallidos));
        $this->assertSame('1 de 1 enviados', (string) MailCampaignResource::progresoConFallidos($sinFallidos->fresh()));
        $this->assertNull(MailCampaignResource::progresoConFallidos($this->campana()), 'un borrador no tiene progreso');

        $this->actingAs($this->admin);
        Livewire::test(ListCampaigns::class)->filterTable('status', MailCampaign::COMPLETADA)
            ->assertSeeHtml('class="fallidos"')->assertSee('1 fallido');
        $conFallidos->recipients()->where('status', MailCampaignRecipient::FALLIDO)->delete();
        $conFallidos->recontar();
        Livewire::test(ListCampaigns::class)->assertDontSeeHtml('class="fallidos"');
    }

    public function test_el_resumen_muestra_lo_secundario_solo_si_es_mayor_que_cero(): void
    {
        $limpia = $this->campana(['status' => MailCampaign::COMPLETADA, 'created_by' => $this->admin->id]);
        $limpia->recipients()->create(['email' => 'ok@gmail.com', 'status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now()]);
        $limpia->recontar();

        $this->ver($limpia)->assertSee('Enviados')->assertSee('Pendientes')->assertSee('Fallidos')
            ->assertSee('Asunto ES')->assertSee($this->admin->full_name)
            // La línea discreta no aparece (el filtro de estado de la tabla sí dice "Omitido").
            ->assertDontSee('0 omitidos')->assertDontSee('1 omitido')->assertDontSee('1 baja')
            ->assertDontSee('manual:')->assertDontSee('claim_token');
        $this->assertNull($limpia->fresh()->detallesSecundarios());

        // Una marca de rebote o queja en la fila (columnas que quedan en la tabla) ya no se muestra.
        $campana = $this->campanaConResultados();
        $campana->recipients()->where('email', 'enviado@gmail.com')->update(['bounced_at' => now(), 'complained_at' => now()]);
        $this->assertSame('2 omitidos · 1 baja', $campana->fresh()->detallesSecundarios());
        $this->ver($campana)->assertSee('2 omitidos · 1 baja')
            ->assertDontSee('rebote')->assertDontSee('Rebote')->assertDontSee('queja')->assertDontSee('Queja');
    }

    public function test_el_motivo_aparece_solo_en_omitidos_y_fallidos(): void
    {
        $campana = $this->campanaConResultados();
        $fila = fn (string $email) => $campana->recipients()->where('email', $email)->sole();
        $this->actingAs($this->admin);

        $this->assertSame([null, 'Se dio de baja', 'Error al enviar', null], [
            $fila('enviado@gmail.com')->motivoLegible(), $fila('baja@gmail.com')->motivoLegible(),
            $fila('falla@gmail.com')->motivoLegible(), $fila('cancelado@gmail.com')->motivoLegible(),
        ]);
        // Rebote y queja (solo si alguien los marca a mano) salen como exclusión genérica.
        foreach (['omitido: rebote' => 'Excluido de promociones', 'omitido: queja de spam' => 'Excluido de promociones', 'omitido: correo sin evidencia de verificación' => 'Sin evidencia de verificación'] as $guardado => $claro) {
            $this->assertSame($claro, (new MailCampaignRecipient(['status' => MailCampaignRecipient::OMITIDO, 'reason' => $guardado]))->motivoLegible());
        }

        Livewire::test(DestinatariosRelationManager::class, ['ownerRecord' => $campana, 'pageClass' => ViewCampaign::class])
            ->assertTableColumnStateSet('motivo', 'Se dio de baja', $fila('baja@gmail.com')->getKey())
            ->assertTableColumnStateSet('motivo', null, $fila('enviado@gmail.com')->getKey())
            ->assertTableColumnStateSet('motivo', null, $fila('cancelado@gmail.com')->getKey())
            ->assertSee('3 intentos')->assertDontSee('0 intentos')
            ->assertTableColumnDoesNotExist('attempts')->assertTableColumnDoesNotExist('reason')
            ->assertSee('enviado@gmail.com')->assertSee('cancelado@gmail.com')
            ->assertTableFilterExists('status')
            ->assertDontSee('Con rebote')->assertDontSee('Con queja')
            ->searchTable('falla@')->assertCanSeeTableRecords([$fila('falla@gmail.com')])->assertCanNotSeeTableRecords([$fila('baja@gmail.com')]);
    }

    public function test_los_avisos_de_descuento_se_ven_igual_de_limpios(): void
    {
        $categoria = Category::create(['name' => 'Cascos Pro', 'slug' => 'cascos-pro', 'is_active' => true, 'discount_percent' => 15, 'discount_active' => true]);
        $aviso = MailCampaign::create(['type' => MailCampaign::TIPO_DESCUENTO, 'name' => 'Descuento en Cascos Pro', 'category_id' => $categoria->id,
            'payload' => app(AvisoDescuento::class)->datos($categoria), 'status' => MailCampaign::COMPLETADA, 'created_by' => $this->admin->id]);
        $aviso->recipients()->create(['email' => 'aviso.cliente@gmail.com', 'status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now()]);
        $aviso->recipients()->create(['email' => 'aviso.baja@gmail.com', 'status' => MailCampaignRecipient::OMITIDO, 'reason' => 'omitido: dado de baja']);
        $aviso->recontar();
        $this->actingAs($this->admin);

        $lista = Livewire::test(ListMailCampaigns::class);
        foreach (['name', 'type', 'status', 'envio', 'progreso'] as $columna) {
            $lista->assertTableColumnExists($columna);
        }
        foreach (['sent_count', 'skipped_count', 'bajas', 'rebotes', 'quejas'] as $retirada) {
            $lista->assertTableColumnDoesNotExist($retirada);
        }
        $lista->assertSee('1 de 2 enviados')->assertSee('Aviso de descuento')->assertDontSeeHtml('class="fallidos"');

        Livewire::test(ViewMailCampaign::class, ['record' => $aviso->getRouteKey()])
            ->assertSee('Cascos Pro con 15 % menos en FatherMotoSport')->assertSee($this->admin->full_name)
            ->assertSee('1 omitido')->assertDontSee('1 baja')->assertDontSee('rebote')->assertDontSee('queja');
        Livewire::test(DestinatariosRelationManager::class, ['ownerRecord' => $aviso, 'pageClass' => ViewMailCampaign::class])
            ->assertSee('aviso.cliente@gmail.com')->assertSee('Se dio de baja')
            ->assertDontSee('Con rebote')->assertDontSee('Con queja');
    }

    public function test_el_webhook_de_resend_ya_no_existe(): void
    {
        $this->postJson('/api/v1/webhooks/resend', ['type' => 'email.bounced'])->assertNotFound();
        $this->assertFalse(class_exists('App\Http\Controllers\Api\ResendWebhookController'));
        $this->assertFalse(class_exists('App\Services\EventosDeResend'));
    }

    public function test_los_logs_no_llevan_correos(): void
    {
        $mensajes = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$mensajes) {
            $mensajes[] = $e->message.' '.json_encode($e->context);
        });
        $this->cliente('secreto.cliente@gmail.com');
        $campana = $this->servicio()->programar($this->campana());
        Mail::shouldReceive('to')->andThrow(new RuntimeException('falló el envío a secreto.cliente@gmail.com'));

        $this->tanda();

        $this->assertNotEmpty($mensajes, 'el fallo se registra');
        $this->assertSame(1, $campana->recipients()->sole()->attempts);
        foreach ($mensajes as $m) {
            $this->assertStringNotContainsString('@gmail.com', $m);
        }
    }
}
