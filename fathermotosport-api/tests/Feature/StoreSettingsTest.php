<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettingsPage;
use App\Mail\EmailVerificationCodeMail;
use App\Mail\PaymentRecoveryMail;
use App\Models\Order;
use App\Models\Role;
use App\Models\StoreConfig;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Configuración de la tienda" como único origen del contacto y las redes: endpoint
 * público con lista cerrada, caché + revalidación de la web, validación del panel,
 * pie de los correos y la baja de las credenciales de pago.
 */
class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/store/settings';

    private const MIGRACION = '2026_10_29_100001_drop_payment_keys_from_store_config_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, StoreConfigSeeder::class]);

        config([
            'services.revalidate.secret' => 'test-secret',
            'app.frontend_url' => 'https://web.test',
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@test.com')->first() ?? User::create([
            'role_id' => Role::where('slug', 'administrador')->firstOrFail()->id,
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);
    }

    /** Formulario del panel con valores válidos, listo para cambiar un campo. */
    private function panel()
    {
        $this->actingAs($this->admin());

        return Livewire::test(StoreSettingsPage::class);
    }

    // ── Endpoint público ──────────────────────────────────────────────────

    public function test_el_endpoint_devuelve_solo_la_lista_cerrada(): void
    {
        Http::fake();
        StoreConfig::current()->update([
            'tiktok' => 'https://www.tiktok.com/@fathermotosport',
            'logo_url' => 'store/logo-secreto.png',
            'currency' => 'BRL',
            'maintenance_mode' => true,
        ]);

        $response = $this->getJson(self::URL)
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'store_name' => 'FatherMotoSport',
                    'email' => 'contacto@fathermotosport.com',
                    'phone' => '+591 68736384',
                    'whatsapp' => '59168736384',
                    'social' => [
                        'facebook' => 'https://facebook.com/fathermotosport',
                        'instagram' => 'https://instagram.com/fathermotosport',
                        'youtube' => 'https://youtube.com/@fathermotosport',
                        'tiktok' => 'https://www.tiktok.com/@fathermotosport',
                    ],
                ],
            ]);

        $body = $response->getContent();
        foreach (['payment_keys', 'logo-secreto', 'BRL', 'maintenance', 'created_at', '"id"'] as $fuga) {
            $this->assertStringNotContainsString($fuga, $body);
        }
    }

    public function test_la_tabla_ya_no_tiene_credenciales_de_pago(): void
    {
        $this->assertFalse(Schema::hasColumn('store_config', 'payment_keys'));
        $this->assertNotContains('payment_keys', (new StoreConfig)->getFillable());
    }

    public function test_un_campo_vacio_llega_como_null(): void
    {
        Http::fake();
        StoreConfig::current()->update([
            'email' => '', 'phone' => '   ', 'whatsapp' => '', 'facebook' => '', 'instagram' => null,
        ]);

        $this->getJson(self::URL)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.whatsapp', null)
            ->assertJsonPath('data.social.facebook', null)
            ->assertJsonPath('data.social.instagram', null)
            ->assertJsonPath('data.social.youtube', 'https://youtube.com/@fathermotosport');
    }

    public function test_un_enlace_guardado_fuera_del_panel_que_no_es_valido_no_se_expone(): void
    {
        // Escrito directo en la base (sin pasar por la validación del panel).
        DB::table('store_config')->update([
            'facebook' => 'http://facebook.com/vieja',
            'instagram' => 'javascript:alert(1)',
            'youtube' => 'https://evil.test/@fathermotosport',
        ]);
        Cache::forget(StoreConfig::CACHE_KEY);

        $this->getJson(self::URL)
            ->assertJsonPath('data.social.facebook', null)
            ->assertJsonPath('data.social.instagram', null)
            ->assertJsonPath('data.social.youtube', null)
            ->assertJsonPath('data.social.tiktok', null);
    }

    public function test_el_whatsapp_se_guarda_solo_con_digitos(): void
    {
        Http::fake();
        StoreConfig::current()->update(['whatsapp' => '+55 11 99999 8888']);

        $this->assertSame('5511999998888', StoreConfig::current()->whatsapp);
    }

    // ── Caché y revalidación ──────────────────────────────────────────────

    public function test_el_endpoint_cachea_y_el_guardado_lo_invalida(): void
    {
        Http::fake();
        $this->getJson(self::URL)->assertJsonPath('data.email', 'contacto@fathermotosport.com');

        // Un cambio que no pasa por el modelo no se ve: la respuesta sale de la caché.
        DB::table('store_config')->update(['email' => 'directo@fathermotosport.com']);
        $this->getJson(self::URL)->assertJsonPath('data.email', 'contacto@fathermotosport.com');

        // Guardar por el modelo (panel, tinker, seeder) la borra.
        StoreConfig::current()->update(['email' => 'nuevo@fathermotosport.com']);
        $this->getJson(self::URL)->assertJsonPath('data.email', 'nuevo@fathermotosport.com');
    }

    public function test_guardar_en_el_panel_borra_la_cache_revalida_la_web_y_avisa(): void
    {
        Http::fake(['web.test/*' => Http::response(['revalidated' => true])]);
        $this->getJson(self::URL)->assertJsonPath('data.social.facebook', 'https://facebook.com/fathermotosport');

        $this->panel()
            ->set('data.facebook', 'https://www.facebook.com/FatherMotoSportOficial')
            ->set('data.whatsapp', '+591 7000 1234')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado. El sitio ya muestra los cambios.')
            ->assertSet('data.whatsapp', '59170001234');

        $this->getJson(self::URL)
            ->assertJsonPath('data.social.facebook', 'https://www.facebook.com/FatherMotoSportOficial')
            ->assertJsonPath('data.whatsapp', '59170001234');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://web.test/api/revalidate'
            && $request->header('x-revalidate-secret') === ['test-secret']
            && $request['tag'] === 'store-settings');
    }

    public function test_si_la_web_no_confirma_guarda_igual_avisa_y_registra_el_error(): void
    {
        Http::fake(['web.test/*' => Http::response('boom', 500)]);
        Log::spy();

        $this->panel()
            ->set('data.instagram', 'https://instagram.com/otra')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado, pero el sitio no confirmó la actualización');

        $this->assertSame('https://instagram.com/otra', StoreConfig::current()->instagram);

        Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto) => $contexto['tag'] === 'store-settings'
            && $contexto['url'] === 'https://web.test/api/revalidate'
            && $contexto['status'] === 500);
    }

    // ── Validación del panel ──────────────────────────────────────────────

    public static function enlacesInvalidos(): array
    {
        return [
            'http' => ['facebook', 'http://facebook.com/fathermotosport'],
            'javascript' => ['facebook', 'javascript:alert(1)'],
            'otro esquema' => ['instagram', 'ftp://instagram.com/fathermotosport'],
            'sin esquema' => ['instagram', 'instagram.com/fathermotosport'],
            'otro dominio' => ['youtube', 'https://evil.test/@fathermotosport'],
            'dominio parecido' => ['facebook', 'https://facebook.com.evil.test/x'],
            'subdominio no permitido' => ['tiktok', 'https://evil.tiktok.com/@x'],
            'dominio de otra red' => ['tiktok', 'https://instagram.com/fathermotosport'],
            'con usuario' => ['youtube', 'https://youtube.com@evil.test/x'],
            'con puerto' => ['instagram', 'https://instagram.com:8443/x'],
        ];
    }

    #[DataProvider('enlacesInvalidos')]
    public function test_el_panel_rechaza_enlaces_no_permitidos(string $red, string $url): void
    {
        Http::fake();

        $this->panel()
            ->set("data.{$red}", $url)
            ->call('save')
            ->assertHasErrors(["data.{$red}"]);

        $this->assertNotSame($url, StoreConfig::current()->getAttribute($red));
        Http::assertNothingSent();
    }

    public function test_el_panel_acepta_las_variantes_habituales_de_cada_red(): void
    {
        Http::fake();

        foreach ([
            'facebook' => ['https://facebook.com/a', 'https://www.facebook.com/a', 'https://fb.com/a', 'https://m.facebook.com/a'],
            'instagram' => ['https://instagram.com/a', 'https://www.instagram.com/a'],
            'youtube' => ['https://youtube.com/@a', 'https://www.youtube.com/@a', 'https://youtu.be/a'],
            'tiktok' => ['https://tiktok.com/@a', 'https://www.tiktok.com/@a'],
        ] as $red => $urls) {
            foreach ($urls as $url) {
                $this->panel()->set("data.{$red}", $url)->call('save')->assertHasNoErrors();
                $this->assertSame($url, StoreConfig::current()->getAttribute($red));
            }
        }
    }

    public static function whatsappsInvalidos(): array
    {
        return [
            '7 dígitos' => ['1234567'],
            '16 dígitos' => ['1234567890123456'],
            'con guiones' => ['+591-68736384'],
            'con letras' => ['59168736384a'],
            'dos más' => ['++59168736384'],
        ];
    }

    #[DataProvider('whatsappsInvalidos')]
    public function test_el_panel_rechaza_un_whatsapp_con_formato_invalido(string $numero): void
    {
        Http::fake();

        $this->panel()->set('data.whatsapp', $numero)->call('save')->assertHasErrors(['data.whatsapp']);

        $this->assertSame('59168736384', StoreConfig::current()->whatsapp);
    }

    public function test_un_whatsapp_vacio_se_guarda_como_null(): void
    {
        Http::fake();

        $this->panel()->set('data.whatsapp', '')->set('data.facebook', '')->call('save')->assertHasNoErrors();

        $this->assertNull(StoreConfig::current()->whatsapp);
        $this->assertNull(StoreConfig::current()->facebook);
    }

    public function test_el_panel_ya_no_tiene_credenciales_de_pago(): void
    {
        $pagina = $this->panel()->assertOk();

        foreach (['paypal_mode', 'paypal_client_id', 'paypal_client_secret', 'stripe_key', 'stripe_secret', 'stripe_webhook_secret', 'mercadopago_public_key', 'mercadopago_access_token'] as $campo) {
            $pagina->assertFormFieldDoesNotExist($campo);
        }

        $pagina->assertDontSee('Credenciales de pago')->assertDontSee('cifrad');
    }

    // ── Correos ───────────────────────────────────────────────────────────

    private function html(Mailable $correo): string
    {
        return html_entity_decode($correo->render(), ENT_QUOTES);
    }

    public function test_el_pie_de_los_correos_usa_el_whatsapp_del_panel(): void
    {
        Http::fake();
        StoreConfig::current()->update(['whatsapp' => '+55 11 99999 8888']);

        $html = $this->html(new EmailVerificationCodeMail('123456'));

        $this->assertStringContainsString('href="https://wa.me/5511999998888"', $html);
        $this->assertStringNotContainsString('wa.me/59168736384', $html);
    }

    public function test_sin_whatsapp_en_el_panel_el_pie_no_muestra_el_enlace(): void
    {
        Http::fake();
        StoreConfig::current()->update(['whatsapp' => null]);

        $html = $this->html(new EmailVerificationCodeMail('123456'));

        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringContainsString('class="footer-link">'.'Ver tienda', $html);
    }

    public function test_sin_configuracion_el_pie_usa_los_valores_de_respaldo(): void
    {
        StoreConfig::query()->delete();

        $this->assertStringContainsString('href="https://wa.me/59168736384"', $this->html(new EmailVerificationCodeMail('123456')));
    }

    public function test_el_correo_de_recuperacion_usa_el_correo_del_panel(): void
    {
        Http::fake();
        StoreConfig::current()->update(['email' => 'ayuda@fathermotosport.com']);

        $order = new Order([
            'order_number' => 'FMS-0001', 'subtotal' => 100, 'discount' => 0, 'shipping' => 0,
            'total' => 100, 'guest_email' => 'cliente@example.com', 'locale' => 'es',
        ]);
        $order->id = '00000000-0000-4000-8000-000000000001';
        $order->access_token = 'token';
        $order->setRelation('items', collect());
        $order->setRelation('address', null);
        $order->setRelation('user', null);

        $html = $this->html(new PaymentRecoveryMail($order));

        $this->assertStringContainsString('mailto:ayuda@fathermotosport.com', $html);
        $this->assertStringNotContainsString('contacto@fathermotosport.com', $html);
    }

    // ── Migración ─────────────────────────────────────────────────────────

    public function test_la_migracion_se_puede_reintentar_y_su_down_recrea_la_columna_vacia(): void
    {
        $migracion = require database_path('migrations/'.self::MIGRACION);

        // Ya corrió con RefreshDatabase: volver a correrla no falla.
        $migracion->up();
        $this->assertFalse(Schema::hasColumn('store_config', 'payment_keys'));

        $migracion->down();
        $migracion->down();
        $this->assertTrue(Schema::hasColumn('store_config', 'payment_keys'));
        $this->assertNull(DB::table('store_config')->value('payment_keys'));

        $migracion->up();
        $migracion->up();
        $this->assertFalse(Schema::hasColumn('store_config', 'payment_keys'));
        $this->assertSame('FatherMotoSport', DB::table('store_config')->value('store_name'));
    }
}
