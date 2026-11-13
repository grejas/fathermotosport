<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarHoodiesHeubowTest extends TestCase
{
    use RefreshDatabase;

    private Product $plantilla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');

        $desc = '<p>Sudadera con capucha Divinity Arcángel 10.</p><p>gráficos en negro y blanco.</p><li>Color: Negro y blanco.</li>';
        foreach (['10' => $desc, '05' => '<p>x</p>'] as $n => $d) {
            $p = Product::factory()->create([
                'name' => "Sudadera con Capucha Divinity Arcángel {$n}",
                'slug' => "sudadera-con-capucha-divinity-arcangel-{$n}",
                'color' => 'Negro y blanco',
                'description' => $d,
                'price' => 159.99,
                'sale_price' => null,
                'is_active' => true,
            ]);
            DB::table('product_images')->where('product_id', $p->id)->delete();
            DB::table('product_variants')->where('product_id', $p->id)->delete();
            foreach (['M', 'L', 'XL', 'XXL'] as $t) {
                $p->variants()->create(['size' => $t, 'sku' => $p->sku.'-'.$t, 'stock' => 10, 'is_active' => true]);
            }
            Storage::disk('public')->put("products/a{$n}.jpg", 'x');
            $p->images()->create(['url' => "products/a{$n}.jpg", 'sort_order' => 1, 'is_primary' => true]);
        }
        $this->plantilla = Product::where('slug', 'sudadera-con-capucha-divinity-arcangel-10')->first();
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $antes = Product::count();
        $this->artisan('products:import-heubow')->assertSuccessful();
        $this->assertSame($antes, Product::count());
    }

    public function test_crea_las_nueve_copiando_la_plantilla(): void
    {
        $this->artisan('products:import-heubow', ['--apply' => true])->assertSuccessful();

        $p = Product::with(['variants', 'images'])->where('slug', 'sudadera-con-capucha-heubow-divinity-arcangel-11')->firstOrFail();
        $this->assertSame('Sudadera con Capucha Heubow Divinity Arcángel 11', $p->name);
        $this->assertSame('Negro y naranja', $p->color);
        $this->assertSame($this->plantilla->category_id, $p->category_id);
        $this->assertSame($this->plantilla->brand_id, $p->brand_id);
        $this->assertSame('159.99', $p->price);
        $this->assertTrue($p->is_active);
        $this->assertStringContainsString('Sudadera con capucha Heubow Divinity Arcángel 11.', $p->description);
        $this->assertStringContainsString('en negro y naranja', $p->description);
        $this->assertStringContainsString('Color: Negro y naranja', $p->description);
        $this->assertEqualsCanonicalizing(['M', 'L', 'XL', 'XXL'], $p->variants->pluck('size')->all());
        $this->assertSame(40, $p->variants->sum('stock'));

        // 2 fotos + la del cierre al final, la primera es la principal.
        $imgs = $p->images->sortBy('sort_order')->values();
        $this->assertCount(3, $imgs);
        $this->assertTrue($imgs[0]->is_primary);
        $this->assertStringContainsString('detalle_cierre_cruz', $imgs[2]->url);
        Storage::disk('public')->assertExists($imgs[0]->url);

        // La 19 no tiene cierre y en la 14 la foto principal es la espalda: sin foto del cierre.
        foreach ([14, 19] as $n) {
            $sin = Product::with('images')->where('slug', "sudadera-con-capucha-heubow-divinity-arcangel-{$n}")->firstOrFail();
            $this->assertCount(1, $sin->images);
        }

        // Las ya publicadas reciben la foto del cierre al final.
        $imgs05 = Product::where('slug', 'sudadera-con-capucha-divinity-arcangel-05')->first()->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $imgs05);
        $this->assertStringContainsString('detalle_cierre_cruz', $imgs05->last()->url);
        // y su foto principal pasa a la versión encuadrada, borrando la anterior del disco.
        $this->assertStringContainsString('_encuadre', $imgs05->first()->url);
        $this->assertTrue($imgs05->first()->is_primary);
        Storage::disk('public')->assertMissing('products/a05.jpg');
        Storage::disk('public')->assertExists($imgs05->first()->url);

        $this->assertSame(9, Product::where('slug', 'like', 'sudadera-con-capucha-heubow-%')->count());
    }

    public function test_es_idempotente(): void
    {
        $this->artisan('products:import-heubow', ['--apply' => true])->assertSuccessful();
        $this->artisan('products:import-heubow', ['--apply' => true])->assertSuccessful();

        $this->assertSame(9, Product::where('slug', 'like', 'sudadera-con-capucha-heubow-%')->count());
        $imgs05 = Product::where('slug', 'sudadera-con-capucha-divinity-arcangel-05')->first()->images()->get();
        $this->assertCount(2, $imgs05);
        $this->assertSame(1, $imgs05->filter(fn ($i) => str_contains($i->url, '_encuadre'))->count());
    }
}
