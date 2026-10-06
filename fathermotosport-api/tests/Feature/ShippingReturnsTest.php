<?php

namespace Tests\Feature;

use App\Filament\Pages\ShippingReturnsPage;
use App\Models\Role;
use App\Models\ShippingReturnsSetting;
use App\Models\StoreConfig;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ShippingReturnsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/store/shipping-returns';

    private const XSS = '<h2 onclick="steal()">Título</h2><p>Texto <a href="javascript:alert(1)">malo</a> <a href="https://fathermotosport.com">bueno</a></p><script>alert(1)</script><iframe src="https://evil.test"></iframe>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, StoreConfigSeeder::class]);

        config([
            'services.revalidate.secret' => 'test-secret',
            'app.frontend_url' => 'https://web.test',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'first_name' => ucfirst($role),
            'last_name' => 'Test',
            'email' => "{$role}@test.com",
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);
    }

    private function assertSanitized(?string $html): void
    {
        $this->assertNotNull($html);
        foreach (['<script', 'alert(1)', '<iframe', 'onclick', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }
        $this->assertStringContainsString('<h2>Título</h2>', $html);
        $this->assertStringContainsString('href="https://fathermotosport.com"', $html);
    }

    // ── Endpoint ───────────────────────────────────────────────────────────

    public function test_la_migracion_deja_la_politica_vigente_en_formato_del_editor(): void
    {
        $response = $this->getJson(self::URL.'?locale=es')
            ->assertOk()
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.damage_report_hours', 48)
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones')
            ->assertJsonPath('data.texts.badge_returns', 'Devolución gratuita si llega dañado o incorrecto');

        $body = $response->json('data.texts.page_body');
        $this->assertStringStartsWith('<p>Envío gratuito a toda América y Europa.', $body);
        $this->assertStringContainsString('<h2>Cuándo aceptamos devoluciones</h2><ul><li>', $body);
        $this->assertStringContainsString('<h2>Cómo funciona el proceso</h2><ol>', $body);
        $this->assertStringContainsString('es de {hours} horas desde la entrega', $body);
        $this->assertStringContainsString('tienes {hours} horas', $response->json('data.texts.summary'));
    }

    public function test_no_queda_nada_del_derecho_de_arrepentimiento(): void
    {
        $this->assertFalse(Schema::hasColumn('shipping_returns_settings', 'withdrawal_days'));

        foreach (ShippingReturnsSetting::LOCALES as $locale) {
            $response = $this->getJson(self::URL."?locale={$locale}")->assertOk();
            $this->assertArrayNotHasKey('withdrawal_days', $response->json('data'));

            $json = $response->getContent();
            foreach (['{days}', 'Derecho de arrepentimiento', 'Direito de arrependimento', 'Right to change your mind', '7 días', '7 dias', '7 days'] as $old) {
                $this->assertStringNotContainsString($old, $json, "{$locale}: {$old}");
            }
        }
    }

    public function test_devuelve_cada_idioma_y_usa_espanol_si_el_idioma_no_existe(): void
    {
        $this->getJson(self::URL.'?locale=pt')
            ->assertJsonPath('data.locale', 'pt')
            ->assertJsonPath('data.texts.page_title', 'Política de Frete e Devoluções');
        $this->assertStringContainsString('<h2>Quando aceitamos devoluções</h2>', $this->getJson(self::URL.'?locale=pt')->json('data.texts.page_body'));

        $this->getJson(self::URL.'?locale=en')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.texts.page_title', 'Shipping and Returns Policy');
        $this->assertStringContainsString('<h2>When we accept returns</h2>', $this->getJson(self::URL.'?locale=en')->json('data.texts.page_body'));

        $this->getJson(self::URL.'?locale=fr')
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        $this->getJson(self::URL)->assertJsonPath('data.locale', 'es');
    }

    public function test_los_campos_vacios_salen_como_null(): void
    {
        ShippingReturnsSetting::current()->update([
            'summary' => ['es' => '   ', 'pt' => null, 'en' => 'Kept'],
            // Lo que deja el editor cuando se borra todo: HTML sin texto visible.
            'page_body' => ['es' => '<p><br></p>', 'pt' => '<div> &nbsp; </div>', 'en' => '<p>Body</p>'],
            'badge_returns' => null,
        ]);

        $this->getJson(self::URL.'?locale=es')
            ->assertJsonPath('data.texts.summary', null)
            ->assertJsonPath('data.texts.page_body', null)
            ->assertJsonPath('data.texts.badge_returns', null)
            // Los demás campos siguen con su texto.
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        $this->getJson(self::URL.'?locale=pt')
            ->assertJsonPath('data.texts.summary', null)
            ->assertJsonPath('data.texts.page_body', null);

        $this->getJson(self::URL.'?locale=en')
            ->assertJsonPath('data.texts.summary', 'Kept')
            ->assertJsonPath('data.texts.page_body', '<p>Body</p>');
    }

    public function test_sin_fila_responde_todo_null_en_vez_de_fallar(): void
    {
        ShippingReturnsSetting::query()->delete();

        $this->getJson(self::URL.'?locale=es')
            ->assertOk()
            ->assertJsonPath('data.damage_report_hours', null)
            ->assertJsonPath('data.texts.page_title', null)
            ->assertJsonPath('data.texts.page_body', null);
    }

    public function test_el_endpoint_sanitiza_html_escrito_fuera_del_panel(): void
    {
        ShippingReturnsSetting::current()->update(['page_body' => ['es' => self::XSS]]);

        $this->assertSanitized($this->getJson(self::URL.'?locale=es')->json('data.texts.page_body'));
    }

    public function test_expone_el_contacto_pero_nunca_las_credenciales_de_pago(): void
    {
        StoreConfig::current()->update([
            'payment_keys' => [
                'stripe' => ['public_key' => 'pk_test_PUBLICA', 'secret_key' => 'sk_test_SECRETA', 'webhook_secret' => 'whsec_SECRETO'],
                'paypal' => ['mode' => 'live', 'client_id' => 'pp_ID', 'secret' => 'pp_SECRETO'],
            ],
        ]);

        $response = $this->getJson(self::URL.'?locale=es')
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'contacto@fathermotosport.com')
            ->assertJsonPath('data.contact.whatsapp', '+59168736384');

        $this->assertSame(['email', 'whatsapp'], array_keys($response->json('data.contact')));

        $body = $response->getContent();
        foreach (['payment_keys', 'sk_test_SECRETA', 'whsec_SECRETO', 'pp_SECRETO', 'pk_test_PUBLICA', 'pp_ID'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }

    // ── Panel: guardado, sanitización, caché y revalidación ───────────────

    public function test_guardar_invalida_la_cache_del_endpoint_y_revalida_la_web(): void
    {
        Http::fake(['web.test/*' => Http::response(['revalidated' => true])]);
        $this->actingAs($this->user('administrador'));

        // Primera lectura: queda en caché.
        $this->getJson(self::URL.'?locale=es')->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.es.page_title', 'Devoluciones')
            ->set('data.es.page_body', '<h2>Plazo</h2><ul><li>Tienes <strong>{hours} horas</strong>.</li></ul>')
            ->set('data.damage_report_hours', 72)
            ->set('data.pt.summary', '  Resumo novo  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado. El sitio ya muestra los cambios.');

        $this->getJson(self::URL.'?locale=es')
            ->assertJsonPath('data.texts.page_title', 'Devoluciones')
            ->assertJsonPath('data.texts.page_body', '<h2>Plazo</h2><ul><li>Tienes <strong>{hours} horas</strong>.</li></ul>')
            ->assertJsonPath('data.damage_report_hours', 72);

        $this->getJson(self::URL.'?locale=pt')->assertJsonPath('data.texts.summary', 'Resumo novo');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://web.test/api/revalidate'
            && $request->header('x-revalidate-secret') === ['test-secret']
            && $request['tag'] === 'shipping-returns');
    }

    public function test_el_panel_guarda_el_cuerpo_sanitizado(): void
    {
        Http::fake();
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.es.page_body', self::XSS)
            ->call('save')
            ->assertHasNoErrors();

        // Ya en la base de datos, no solo al servirlo.
        $this->assertSanitized(ShippingReturnsSetting::current()->page_body['es']);
    }

    public function test_si_la_web_responde_error_guarda_igual_y_avisa(): void
    {
        Http::fake(['web.test/*' => Http::response('boom', 500)]);
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.es.page_title', 'Título nuevo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado, pero el sitio no confirmó la actualización');

        $this->assertSame('Título nuevo', ShippingReturnsSetting::current()->page_title['es']);
    }

    public function test_si_la_web_no_responde_guarda_igual_y_avisa(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.en.badge_returns', 'Free returns on faulty items')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado, pero el sitio no confirmó la actualización');

        $this->assertSame('Free returns on faulty items', ShippingReturnsSetting::current()->badge_returns['en']);
    }

    public function test_sin_secreto_no_llama_a_la_web_y_avisa(): void
    {
        config(['services.revalidate.secret' => null]);
        Http::fake();
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado, pero el sitio no confirmó la actualización');

        Http::assertNothingSent();
    }

    public function test_el_formulario_carga_los_textos_vigentes(): void
    {
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->assertSet('data.damage_report_hours', 48)
            ->assertSet('data.en.page_title', 'Shipping and Returns Policy')
            ->assertSet('data.pt.badge_returns', 'Devolução grátis se chegar danificado ou errado')
            ->assertSet('data.es.page_body', ShippingReturnsSetting::current()->page_body['es']);
    }

    public function test_guardar_sin_cambios_no_altera_la_politica_inicial(): void
    {
        Http::fake();
        $this->actingAs($this->user('administrador'));
        $before = ShippingReturnsSetting::current()->only(['badge_returns', 'summary', 'page_title', 'page_body']);

        Livewire::test(ShippingReturnsPage::class)->call('save')->assertHasNoErrors();

        $this->assertSame($before, ShippingReturnsSetting::current()->only(['badge_returns', 'summary', 'page_title', 'page_body']));
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function test_administrador_y_empleado_pueden_editar_la_politica(): void
    {
        Http::fake();

        $this->actingAs($this->user('administrador'));
        $this->assertTrue(ShippingReturnsPage::canAccess());

        $this->actingAs($this->user('empleado'));
        $this->assertTrue(ShippingReturnsPage::canAccess());

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.es.badge_returns', 'Devolución sin costo si llega con fallas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Devolución sin costo si llega con fallas', ShippingReturnsSetting::current()->badge_returns['es']);
    }

    public function test_cliente_e_invitado_no_pueden_editar_la_politica(): void
    {
        $this->assertFalse(ShippingReturnsPage::canAccess());

        $this->actingAs($this->user('cliente'));
        $this->assertFalse(ShippingReturnsPage::canAccess());
    }
}
