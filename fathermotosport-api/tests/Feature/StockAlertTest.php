<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Avísame cuando esté disponible» en productos agotados. */
class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function producto(): Product
    {
        $p = Product::factory()->create();
        $p->variants()->update(['stock' => 0]);

        return $p;
    }

    private function enviar(Product $p, array $datos = [], string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/api/v1/products/{$p->slug}/stock-alert", array_merge([
                'email' => 'Cliente@Gmail.com',
                'whatsapp' => '+591 68736384',
                'size' => 'L',
                'locale' => 'es',
            ], $datos));
    }

    public function test_registra_el_aviso_con_talla(): void
    {
        $p = $this->producto();

        $this->enviar($p)->assertCreated()->assertJsonPath('status', 'new');

        $a = StockAlert::firstOrFail();
        $this->assertSame($p->id, $a->product_id);
        $this->assertSame('L', $a->size);
        $this->assertSame('cliente@gmail.com', $a->email);
        $this->assertNull($a->notified_at);
        $this->assertNotSame('10.0.0.1', $a->ip_hash, 'No se guarda la IP en claro');
    }

    public function test_repetir_no_duplica_y_vuelve_a_pendiente(): void
    {
        $p = $this->producto();
        $this->enviar($p)->assertCreated();
        StockAlert::query()->update(['notified_at' => now()]);

        $this->enviar($p, ['whatsapp' => '+591 70000000'])->assertOk()->assertJsonPath('status', 'existing');

        $this->assertSame(1, StockAlert::count());
        $a = StockAlert::first();
        $this->assertNull($a->notified_at);
        $this->assertSame('+591 70000000', $a->whatsapp);
    }

    public function test_otra_talla_es_otro_aviso(): void
    {
        $p = $this->producto();
        $this->enviar($p)->assertCreated();
        $this->enviar($p, ['size' => 'M'])->assertCreated();

        $this->assertSame(2, StockAlert::count());
    }

    public function test_valida_email_y_trampa_de_bots(): void
    {
        $p = $this->producto();

        $this->enviar($p, ['email' => 'no-es-email'])->assertUnprocessable();
        $this->enviar($p, ['website' => 'spam.com'])->assertUnprocessable();
        $this->assertSame(0, StockAlert::count());
    }

    public function test_producto_inactivo_o_inexistente_da_404(): void
    {
        $p = $this->producto();
        $p->update(['is_active' => false]);

        $this->enviar($p)->assertNotFound();
        $this->postJson('/api/v1/products/no-existe/stock-alert', ['email' => 'a@b.com'])->assertNotFound();
    }

    public function test_el_panel_lista_los_avisos(): void
    {
        $p = $this->producto();
        $this->enviar($p)->assertCreated();

        $admin = User::where('email', 'admin@fathermotosport.com')->firstOrFail();
        $this->actingAs($admin)->get('/admin/stock-alerts')
            ->assertOk()
            ->assertSee('cliente@gmail.com')
            ->assertSee($p->name);
    }
}
