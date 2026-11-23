<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    public function test_la_migracion_agota_el_casco_agv_world_title(): void
    {
        $p = Product::factory()->create(['slug' => 'casco-agv-pista-gp-r-world-title-2002']);
        $p->variants()->update(['stock' => 4]);
        $antes = (int) $p->variants()->sum('stock');

        $migracion = require database_path('migrations/2026_11_23_100002_agotar_casco_agv_pista_gp_r_world_title_2002.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertSame(0, (int) $p->variants()->sum('stock'));
        $this->assertSame(1, $p->inventoryMovements()->count());
        $this->assertSame(-$antes, $p->inventoryMovements()->first()->quantity);
    }

    public function test_la_migracion_deja_10_por_talla_en_el_world_title_2003(): void
    {
        $p = Product::factory()->create(['slug' => 'casco-agv-pista-gp-r-world-title-2003']);
        $p->variants()->update(['stock' => 0]);
        $p->variants()->first()->update(['stock' => 1]);
        $tallas = $p->variants()->count();

        $migracion = require database_path('migrations/2026_11_23_100003_stock_10_casco_agv_pista_gp_r_world_title_2003.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertSame([10], $p->variants()->pluck('stock')->unique()->values()->all());
        $this->assertSame(1, $p->inventoryMovements()->count());
        $this->assertSame(10 * $tallas - 1, $p->inventoryMovements()->first()->quantity);
    }

    public function test_la_migracion_agota_el_rossi_mugello_2019(): void
    {
        $p = Product::factory()->create(['slug' => 'casco-agv-pista-gp-r-rossi-mugello-2019']);
        $p->variants()->update(['stock' => 5]);

        $migracion = require database_path('migrations/2026_11_23_100004_agotar_casco_agv_pista_gp_r_rossi_mugello_2019.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertSame(0, (int) $p->variants()->sum('stock'));
        $this->assertSame(1, $p->inventoryMovements()->count());
    }

    public function test_la_migracion_agota_el_ruroc_atlas_fenrir(): void
    {
        $p = Product::factory()->create(['slug' => 'casco-ruroc-atlas-40-fenrir']);
        $p->variants()->update(['stock' => 3]);

        $migracion = require database_path('migrations/2026_11_23_100005_agotar_casco_ruroc_atlas_40_fenrir.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertSame(0, (int) $p->variants()->sum('stock'));
        $this->assertSame(1, $p->inventoryMovements()->count());
    }

    public function test_la_migracion_agrega_xxl_al_rossi_winter_test_2017(): void
    {
        $p = Product::factory()->create(['slug' => 'casco-agv-pista-gp-r-rossi-winter-test-2017-tavullia', 'sku' => 'FMS-AGV-CQMASX']);
        $p->variants()->where('size', 'XXL')->delete();

        $migracion = require database_path('migrations/2026_11_23_100006_xxl_casco_agv_pista_gp_r_rossi_winter_test_2017_tavullia.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $xxl = $p->variants()->where('size', 'XXL')->get();
        $this->assertCount(1, $xxl);
        $this->assertSame(5, $xxl->first()->stock);
        $this->assertSame('FMS-AGV-CQMASX-XXL', $xxl->first()->sku);
        $this->assertTrue($xxl->first()->is_active);
        $this->assertSame(1, $p->inventoryMovements()->count());
    }

    public function test_la_migracion_da_tallas_m_l_xxl_a_los_alerones(): void
    {
        $aleron = Product::factory()->create(['slug' => 'aleron-de-casco-agv-pista-gp-r-joan-mir', 'sku' => 'FMS-AGV-W9OAYD']);
        $aleron->variants()->delete();
        $original = $aleron->variants()->create(['size' => null, 'sku' => 'FMS-AGV-W9OAYD', 'stock' => 7, 'is_active' => true]);
        $casco = Product::factory()->create(['slug' => 'casco-agv-pista-gp-rr-performante']);
        $tallasCasco = $casco->variants()->pluck('stock', 'size')->all();

        $migracion = require database_path('migrations/2026_11_23_100007_tallas_m_l_xxl_en_alerones.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertSame(['L' => 7, 'M' => 7, 'XXL' => 7], $aleron->variants()->orderBy('size')->pluck('stock', 'size')->all());
        $this->assertSame('M', $original->fresh()->size, 'La variante original pasa a ser la M');
        $this->assertSame('FMS-AGV-W9OAYD-XXL', $aleron->variants()->where('size', 'XXL')->value('sku'));
        $this->assertSame(14, $aleron->inventoryMovements()->sum('quantity'));
        $this->assertSame(1, $aleron->inventoryMovements()->count());
        $this->assertSame($tallasCasco, $casco->variants()->pluck('stock', 'size')->all(), 'Los cascos no se tocan');
    }

    public function test_el_panel_ofrece_tallas_a_un_aleron_de_una_categoria_sin_tallas(): void
    {
        $repuestos = Category::create(['name' => 'Repuestos test', 'slug' => 'repuestos-test', 'size_type' => Category::SIZE_NONE]);
        $aleron = Product::factory()->create(['slug' => 'aleron-de-casco-test', 'sku' => 'FMS-ALE-TEST', 'category_id' => $repuestos->id]);
        $aleron->variants()->delete();
        $m = $aleron->variants()->create(['size' => 'M', 'sku' => 'FMS-ALE-TEST-M', 'stock' => 3, 'is_active' => true]);

        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $opciones = null;
        Livewire::test(EditProduct::class, ['record' => $aleron->getRouteKey()])
            ->assertFormFieldExists("variants.record-{$m->id}.size", function (Select $campo) use (&$opciones) {
                $opciones = array_keys($campo->getOptions());

                return true;
            });

        $this->assertContains('L', $opciones);
        $this->assertContains('XXL', $opciones);
    }

    public function test_la_migracion_agrega_el_video_de_la_supertech_air_primero_en_la_galeria(): void
    {
        Storage::fake('public');
        $p = Product::factory()->create(['slug' => 'chaqueta-alpinestars-t-supertech-air-negro']);
        $portada = $p->primary_image;

        $migracion = require database_path('migrations/2026_11_23_100008_video_chaqueta_alpinestars_t_supertech_air_negro.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $galeria = $p->images()->orderBy('sort_order')->get();
        $video = $galeria->first();
        $this->assertSame(1, $p->images()->where('media_type', 'video')->count());
        $this->assertSame('video', $video->media_type);
        $this->assertSame('products/videos/supertech-air-negro-poster.jpg', $video->poster_url);
        $this->assertFalse($video->with_sound);
        Storage::disk('public')->assertExists('products/videos/supertech-air-negro.mp4');
        $this->assertSame($portada, $p->fresh()->primary_image, 'La portada del catálogo no cambia');
    }
}
