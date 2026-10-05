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
use Livewire\Livewire;
use Tests\TestCase;

class ShippingReturnsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/store/shipping-returns';

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

    // ── Endpoint ───────────────────────────────────────────────────────────

    public function test_la_migracion_deja_los_textos_vigentes_con_sus_plazos(): void
    {
        $this->getJson(self::URL.'?locale=es')
            ->assertOk()
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.damage_report_hours', 48)
            ->assertJsonPath('data.withdrawal_days', 7)
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones')
            ->assertJsonPath('data.texts.badge_returns', 'Devolución gratuita')
            ->assertJsonCount(4, 'data.texts.damaged_items')
            ->assertJsonPath('data.texts.damaged_items.0', 'El plazo para reportar un artículo dañado o defectuoso es de {hours} horas desde la entrega.');
    }

    public function test_devuelve_cada_idioma_y_usa_espanol_si_el_idioma_no_existe(): void
    {
        $this->getJson(self::URL.'?locale=pt')
            ->assertJsonPath('data.locale', 'pt')
            ->assertJsonPath('data.texts.page_title', 'Política de Frete e Devoluções');

        $this->getJson(self::URL.'?locale=en')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.texts.page_title', 'Shipping and Returns Policy');

        $this->getJson(self::URL.'?locale=fr')
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        $this->getJson(self::URL)->assertJsonPath('data.locale', 'es');
    }

    public function test_los_campos_vacios_salen_como_null(): void
    {
        $setting = ShippingReturnsSetting::current();
        $setting->update([
            'summary_damaged' => ['es' => '   ', 'pt' => null, 'en' => 'Kept'],
            'process_items' => ['es' => ['', '  '], 'pt' => [], 'en' => ['One']],
            'help_text' => null,
        ]);

        $this->getJson(self::URL.'?locale=es')
            ->assertJsonPath('data.texts.summary_damaged', null)
            ->assertJsonPath('data.texts.process_items', null)
            ->assertJsonPath('data.texts.help_text', null)
            // Los demás campos siguen con su texto.
            ->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        $this->getJson(self::URL.'?locale=pt')
            ->assertJsonPath('data.texts.summary_damaged', null)
            ->assertJsonPath('data.texts.process_items', null);

        $this->getJson(self::URL.'?locale=en')
            ->assertJsonPath('data.texts.summary_damaged', 'Kept')
            ->assertJsonPath('data.texts.process_items', ['One']);
    }

    public function test_sin_fila_responde_todo_null_en_vez_de_fallar(): void
    {
        ShippingReturnsSetting::query()->delete();

        $this->getJson(self::URL.'?locale=es')
            ->assertOk()
            ->assertJsonPath('data.damage_report_hours', null)
            ->assertJsonPath('data.texts.page_title', null)
            ->assertJsonPath('data.texts.damaged_items', null);
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

    // ── Panel: guardado, caché y revalidación ─────────────────────────────

    public function test_guardar_invalida_la_cache_del_endpoint_y_revalida_la_web(): void
    {
        Http::fake(['web.test/*' => Http::response(['revalidated' => true])]);
        $this->actingAs($this->user('administrador'));

        // Primera lectura: queda en caché.
        $this->getJson(self::URL.'?locale=es')->assertJsonPath('data.texts.page_title', 'Política de Envío y Devoluciones');

        Livewire::test(ShippingReturnsPage::class)
            ->set('data.es.page_title', 'Envíos y cambios')
            ->set('data.withdrawal_days', 10)
            ->set('data.pt.process_items', "  Passo um  \n\n Passo dois \n")
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado. El sitio ya muestra los cambios.');

        $this->getJson(self::URL.'?locale=es')
            ->assertJsonPath('data.texts.page_title', 'Envíos y cambios')
            ->assertJsonPath('data.withdrawal_days', 10);

        $this->getJson(self::URL.'?locale=pt')
            ->assertJsonPath('data.texts.process_items', ['Passo um', 'Passo dois']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://web.test/api/revalidate'
            && $request->header('x-revalidate-secret') === ['test-secret']
            && $request['tag'] === 'shipping-returns');
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
            ->set('data.en.badge_shipping', 'Shipping on us')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Guardado, pero el sitio no confirmó la actualización');

        $this->assertSame('Shipping on us', ShippingReturnsSetting::current()->badge_shipping['en']);
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

    public function test_el_formulario_carga_los_textos_y_las_listas_por_linea(): void
    {
        $this->actingAs($this->user('administrador'));

        Livewire::test(ShippingReturnsPage::class)
            ->assertSet('data.damage_report_hours', 48)
            ->assertSet('data.en.page_title', 'Shipping and Returns Policy')
            ->assertSet('data.es.cancellations_items', "El pedido puede cancelarse en cualquier momento antes de que comience a procesarse para su envío.\nSi el pedido ya fue despachado, se aplica el proceso de devolución descrito arriba.");
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
            ->set('data.es.badge_shipping', 'Envío sin costo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Envío sin costo', ShippingReturnsSetting::current()->badge_shipping['es']);
    }

    public function test_cliente_e_invitado_no_pueden_editar_la_politica(): void
    {
        $this->assertFalse(ShippingReturnsPage::canAccess());

        $this->actingAs($this->user('cliente'));
        $this->assertFalse(ShippingReturnsPage::canAccess());
    }
}
