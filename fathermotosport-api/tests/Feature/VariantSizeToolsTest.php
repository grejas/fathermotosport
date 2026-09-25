<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\SizeCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VariantSizeToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ProductFactory agrega 2-4 variantes aleatorias; se borran para partir de un producto sin variantes.
    private function bareProduct(string $sku, string $categorySlug = 'cascos'): Product
    {
        $product = Product::factory()->create([
            'sku' => $sku,
            'category_id' => Category::where('slug', $categorySlug)->value('id'),
        ]);
        $product->variants()->delete();

        return $product;
    }

    private function combinedVariant(int $stock = 5): ProductVariant
    {
        $product = $this->bareProduct('FMS-TEST-PROD');

        return ProductVariant::factory()->for($product)->create([
            'size' => 'M, L, XL',
            'sku' => 'FMS-TEST-PROD-ML',
            'stock' => $stock,
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $variant = $this->combinedVariant();

        $this->artisan('products:fix-comma-sizes')
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('FMS-TEST-PROD-M')
            ->assertSuccessful();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'size' => 'M, L, XL']);
        $this->assertSame(1, ProductVariant::where('product_id', $variant->product_id)->count());
    }

    public function test_apply_splits_sizes_and_stock_and_deletes_original(): void
    {
        $variant = $this->combinedVariant(5);

        $this->artisan('products:fix-comma-sizes --apply')->assertSuccessful();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);

        $new = ProductVariant::where('product_id', $variant->product_id)->get()->keyBy('size');
        // El resto de 5÷3 se reparte de a 1 desde la primera talla: 2, 2, 1.
        $this->assertEquals(['M' => 2, 'L' => 2, 'XL' => 1], $new->map(fn ($v) => $v->stock)->all());
        // Patrón del sistema: {SKU_PRODUCTO}-{TALLA}.
        $this->assertSame('FMS-TEST-PROD-XL', $new['XL']->sku);
        $this->assertTrue($new->every(fn ($v) => $v->is_active));
    }

    public function test_apply_skips_when_a_size_already_exists_in_the_product(): void
    {
        $variant = $this->combinedVariant();
        ProductVariant::factory()->for($variant->product)->create(['size' => 'L']);

        $this->artisan('products:fix-comma-sizes --apply')
            ->expectsOutputToContain('Omitida')
            ->assertSuccessful();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_create_product_saves_color_on_product_and_variant_skus_without_color(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        // set() reemplaza las filas del Repeater (fillForm las mezclaría con la fila vacía por defecto).
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Guante Nuevo',
                'slug' => 'guante-nuevo',
                'brand_id' => Brand::value('id'),
                'category_id' => Category::where('slug', 'guantes')->value('id'),
                'sku' => 'FMS-NEW',
                'price' => 50,
                'color' => 'Azul Negro',
            ])
            ->set('data.variants', [
                'a' => ['size' => 'M', 'stock' => 3, 'is_active' => true],
                'b' => ['size' => 'L', 'stock' => 0, 'is_active' => false],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'FMS-NEW')->firstOrFail();
        $this->assertSame('Azul Negro', $product->color);

        $variants = $product->variants()->get()->keyBy('size');
        $this->assertSame('FMS-NEW-M', $variants['M']->sku);
        $this->assertSame('FMS-NEW-L', $variants['L']->sku);
        $this->assertSame(3, $variants['M']->stock);
        $this->assertFalse($variants['L']->is_active);
    }

    public function test_edit_rejects_duplicate_size_and_regenerates_sku_when_size_changes(): void
    {
        $product = $this->bareProduct('FMS-GLV', 'guantes');
        $m = ProductVariant::factory()->for($product)->create(['size' => 'M', 'sku' => 'FMS-GLV-M', 'stock' => 7]);

        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        // Dos filas con la misma talla: error de validación, no un 500 por SKU duplicado.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.variants', [
                'x' => ['size' => 'S', 'stock' => 1, 'is_active' => true],
                'y' => ['size' => 'S', 'stock' => 2, 'is_active' => true],
            ])
            ->call('save')
            ->assertHasFormErrors();
        $this->assertSame(1, $product->variants()->count());

        // Cambiar M → XL en la fila existente regenera su SKU y conserva el stock.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.variants', ["record-{$m->id}" => ['size' => 'XL', 'stock' => 7, 'is_active' => true]])
            ->call('save')
            ->assertHasNoFormErrors();

        $m->refresh();
        $this->assertSame(['XL', 'FMS-GLV-XL', 7], [$m->size, $m->sku, $m->stock]);
    }

    public function test_save_regenerates_any_variant_sku_off_pattern(): void
    {
        $product = $this->bareProduct('FMS-RGN', 'guantes');
        $legacy = ProductVariant::factory()->for($product)->create(['size' => 'M', 'sku' => 'FMS-RGN-M-A']);
        $suffixed = ProductVariant::factory()->for($product)->create(['size' => 'L', 'sku' => 'FMS-RGN-L-2']);

        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        // Guardar sin editar nada: el SKU viejo se corrige; el de sufijo -2 ya cumple el patrón.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('FMS-RGN-M', $legacy->fresh()->sku);
        $this->assertSame('FMS-RGN-L-2', $suffixed->fresh()->sku);

        // Cambiar el SKU del producto regenera el de todas sus variantes.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['sku' => 'FMS-RGN2'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['FMS-RGN2-M', 'FMS-RGN2-L'], $product->variants()->pluck('sku')->all());
    }

    public function test_cart_uses_product_price_and_ignores_legacy_variant_price(): void
    {
        $product = $this->bareProduct('FMS-PRC', 'guantes');
        $product->update(['price' => 430, 'sale_price' => null]);
        // Override viejo guardado directo en la BD: debe ignorarse.
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);
        $variant->forceFill(['price' => 999])->save();

        $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], ['X-Session-Id' => 'test-session'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.price', '430.00');

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertJsonMissingPath('data.variants.0.price')
            ->assertJsonPath('data.price', '430.00');
    }

    public function test_apply_splits_space_separated_sizes_with_product_sku_pattern(): void
    {
        $product = $this->bareProduct('FMS-AGV-SYZGJT', 'guantes');
        $variant = ProductVariant::factory()->for($product)->create([
            'size' => 'M L XL',
            'sku' => 'FMS-AGV-SYZGJT-M L XL-A',
            'stock' => 5,
        ]);

        $this->artisan('products:fix-comma-sizes --apply')->assertSuccessful();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        // {SKU_PRODUCTO}-{TALLA}, sin la letra de color del SKU original.
        $this->assertEquals([
            'M' => 'FMS-AGV-SYZGJT-M',
            'L' => 'FMS-AGV-SYZGJT-L',
            'XL' => 'FMS-AGV-SYZGJT-XL',
        ], $product->variants()->pluck('sku', 'size')->all());
    }

    public function test_space_separated_text_that_is_not_all_sizes_is_left_alone(): void
    {
        $product = $this->bareProduct('FMS-UNI', 'accesorios');
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'Talla única', 'sku' => 'FMS-UNI-1']);
        ProductVariant::factory()->for($product)->create(['size' => '  M  ', 'sku' => 'FMS-UNI-2']);

        $this->artisan('products:fix-comma-sizes --apply')
            ->expectsOutputToContain('No hay variantes')
            ->assertSuccessful();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'size' => 'Talla única']);
    }

    public function test_edit_form_offers_category_sizes_and_keeps_non_standard_value(): void
    {
        $product = $this->bareProduct('FMS-EDIT', 'botas');
        ProductVariant::factory()->for($product)->create(['size' => 'M L XL', 'sku' => 'FMS-EDIT-1']);

        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail())
            ->get("/admin/products/{$product->getRouteKey()}/edit")
            ->assertOk()
            ->assertSee('M L XL (no estándar)')
            ->assertSee('36')
            ->assertSee('47');
    }

    public function test_size_catalog_maps_categories_and_inherits_from_parent(): void
    {
        $id = fn (string $slug) => Category::where('slug', $slug)->value('id');

        $this->assertSame(SizeCatalog::LETTERS, SizeCatalog::forCategoryId($id('cascos')));
        $this->assertSame(SizeCatalog::LETTERS, SizeCatalog::forCategoryId($id('guantes')));
        $this->assertSame(SizeCatalog::LETTERS, SizeCatalog::forCategoryId($id('chamarras')));
        $this->assertSame(range(36, 47), array_map('intval', SizeCatalog::forCategoryId($id('botas'))));
        $this->assertSame([], SizeCatalog::forCategoryId($id('viseras')));
        $this->assertSame([], SizeCatalog::forCategoryId($id('repuestos')));
        $this->assertSame([], SizeCatalog::forCategoryId($id('accesorios')));
        $this->assertSame([], SizeCatalog::forCategoryId(null));

        $sub = Category::create(['name' => 'Botas Off-Road', 'slug' => 'botas-offroad', 'parent_id' => $id('botas')]);
        $this->assertSame(SizeCatalog::bootSizes(), SizeCatalog::forCategoryId($sub->id));
    }
}

