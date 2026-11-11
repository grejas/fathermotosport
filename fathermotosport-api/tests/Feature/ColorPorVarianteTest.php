<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Productos con varios colores elegibles en el mismo enlace (guantes en rojo y en negro):
 * el color vive en product_variants.color y las fotos se marcan con product_images.color.
 * Los productos de un solo color (casi todo el catálogo) no cambian.
 */
class ColorPorVarianteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function producto(): Product
    {
        $producto = Product::factory()->create(['is_active' => true, 'sku' => 'FMS-GL-0001', 'color' => 'Naranja', 'price' => 100, 'sale_price' => null, 'weight' => 1]);
        DB::table('product_images')->where('product_id', $producto->id)->delete();
        $producto->variants()->delete();

        return $producto->fresh();
    }

    public function test_the_sku_includes_the_color_only_when_there_is_one(): void
    {
        $this->assertSame('FMS-GL-0001-M', ProductVariant::skuFor('FMS-GL-0001', 'M'));
        $this->assertSame('FMS-GL-0001-ROJO-BLANCO-NEGRO-M', ProductVariant::skuFor('FMS-GL-0001', 'M', 'Rojo / Blanco / Negro'));
        $this->assertSame('FMS-GL-0001-NEGRO', ProductVariant::skuFor('FMS-GL-0001', null, 'Negro'));

        $this->assertTrue(ProductVariant::skuMatchesPattern('FMS-GL-0001-M', 'FMS-GL-0001', 'M'));
        $this->assertFalse(ProductVariant::skuMatchesPattern('FMS-GL-0001-M', 'FMS-GL-0001', 'M', 'Negro'));
        $this->assertTrue(ProductVariant::skuMatchesPattern('FMS-GL-0001-NEGRO-M-2', 'FMS-GL-0001', 'M', 'Negro'));
    }

    public function test_the_api_exposes_the_color_of_each_variant_and_each_photo(): void
    {
        $producto = $this->producto();
        ProductVariant::factory()->for($producto)->create(['size' => 'M', 'color' => 'Negro', 'stock' => 4, 'sku' => 'T-1']);
        ProductVariant::factory()->for($producto)->create(['size' => 'M', 'color' => null, 'stock' => 4, 'sku' => 'T-2']);
        ProductImage::create(['product_id' => $producto->id, 'url' => 'products/negro.jpg', 'color' => 'Negro', 'sort_order' => 1]);
        ProductImage::create(['product_id' => $producto->id, 'url' => 'products/todos.jpg', 'sort_order' => 2]);

        $data = $this->getJson("/api/v1/products/{$producto->slug}")->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['Negro', null], collect($data['variants'])->pluck('color')->all());
        $this->assertEqualsCanonicalizing(['Negro', null], collect($data['images'])->pluck('color')->all());
    }

    public function test_the_order_keeps_the_color_that_was_bought(): void
    {
        $producto = $this->producto();
        $rojo = ProductVariant::factory()->for($producto)->create(['size' => 'L', 'color' => 'Rojo / Blanco / Negro', 'stock' => 4, 'sku' => 'T-ROJO']);
        $unico = Product::factory()->create(['color' => 'Verde', 'price' => 100, 'sale_price' => null, 'weight' => 1]);
        $simple = ProductVariant::factory()->for($unico)->create(['size' => 'M', 'color' => null, 'stock' => 4, 'sku' => 'T-VERDE']);

        $this->postJson('/api/v1/orders', [
            'guest_email' => 'cliente@example.com',
            'items' => [
                ['variant_id' => $rojo->id, 'quantity' => 1],
                ['variant_id' => $simple->id, 'quantity' => 1],
            ],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->assertCreated();

        $items = Order::firstOrFail()->items->keyBy('product_variant_id');
        $this->assertSame('Rojo / Blanco / Negro', $items[$rojo->id]->color);
        $this->assertSame('Verde', $items[$simple->id]->color, 'sin color de variante se usa el del producto');
    }

    public function test_the_panel_saves_colors_and_builds_the_sku_with_them(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $producto = $this->producto();

        Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()])
            ->fillForm(['variants' => [
                ['color' => 'Rojo', 'size' => 'M', 'stock' => 4, 'is_active' => true],
                ['color' => 'Rojo', 'size' => 'L', 'stock' => 4, 'is_active' => true],
                ['color' => 'Negro', 'size' => 'M', 'stock' => 4, 'is_active' => true],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $skus = $producto->variants()->orderBy('sku')->pluck('sku')->all();
        $this->assertSame(['FMS-GL-0001-NEGRO-M', 'FMS-GL-0001-ROJO-L', 'FMS-GL-0001-ROJO-M'], $skus);
        $this->assertEqualsCanonicalizing(['Rojo', 'Rojo', 'Negro'], $producto->variants()->pluck('color')->all());
    }

    public function test_the_panel_rejects_repeated_color_and_size_and_a_mix_of_colored_and_plain_rows(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $producto = $this->producto();
        $livewire = Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()]);

        $livewire->fillForm(['variants' => [
            ['color' => 'Rojo', 'size' => 'M', 'stock' => 4, 'is_active' => true],
            ['color' => 'rojo', 'size' => 'M', 'stock' => 2, 'is_active' => true],
        ]])->call('save')->assertHasFormErrors(['variants']);

        $livewire->fillForm(['variants' => [
            ['color' => 'Rojo', 'size' => 'M', 'stock' => 4, 'is_active' => true],
            ['color' => '', 'size' => 'L', 'stock' => 2, 'is_active' => true],
        ]])->call('save')->assertHasFormErrors(['variants']);

        // Una talla se puede repetir si es de otro color.
        $livewire->fillForm(['variants' => [
            ['color' => 'Rojo', 'size' => 'M', 'stock' => 4, 'is_active' => true],
            ['color' => 'Negro', 'size' => 'M', 'stock' => 2, 'is_active' => true],
        ]])->call('save')->assertHasNoFormErrors();
    }

    public function test_single_color_products_keep_their_old_sku_and_no_color(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $producto = $this->producto();

        Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()])
            ->fillForm(['variants' => [
                ['size' => 'M', 'stock' => 4, 'is_active' => true],
                ['size' => 'L', 'stock' => 4, 'is_active' => true],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['FMS-GL-0001-L', 'FMS-GL-0001-M'], $producto->variants()->orderBy('sku')->pluck('sku')->all());
        $this->assertSame([null, null], $producto->variants()->pluck('color')->all());
    }
}
