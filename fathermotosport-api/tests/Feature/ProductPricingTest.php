<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductPricingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Precios y ofertas en lote: reglas del servicio y acciones del panel de Productos.
 */
class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    private ProductPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $this->pricing = app(ProductPricingService::class);
    }

    private function producto(float $price, ?float $sale = null): Product
    {
        return Product::factory()->create(['price' => $price, 'sale_price' => $sale]);
    }

    public function test_set_prices_saves_price_and_sale(): void
    {
        $product = $this->producto(329);

        $this->pricing->setPrices($product, 289.99);

        $this->assertSame('289.99', $product->fresh()->price);
        $this->assertNull($product->fresh()->sale_price);
    }

    public function test_set_prices_rejects_sale_not_below_price(): void
    {
        $product = $this->producto(100);

        $this->expectException(InvalidArgumentException::class);
        $this->pricing->setPrices($product, 100, 100);
    }

    public function test_set_prices_rejects_non_positive_price(): void
    {
        $product = $this->producto(100);

        $this->expectException(InvalidArgumentException::class);
        $this->pricing->setPrices($product, 0);
    }

    public function test_adjust_price_raises_and_drops_stale_sale(): void
    {
        $con = $this->producto(100, 80);
        $sin = $this->producto(200);

        $changed = $this->pricing->adjustPrice([$con, $sin], -25);

        $this->assertSame(2, $changed);
        $this->assertSame('75.00', $con->fresh()->price);
        // La oferta de 80 ya no es menor que 75: se quita.
        $this->assertNull($con->fresh()->sale_price);
        $this->assertSame('150.00', $sin->fresh()->price);
    }

    public function test_adjust_price_keeps_sale_that_is_still_lower(): void
    {
        $product = $this->producto(100, 60);

        $this->pricing->adjustPrice([$product], 10);

        $this->assertSame('110.00', $product->fresh()->price);
        $this->assertSame('60.00', $product->fresh()->sale_price);
    }

    public function test_apply_discount_sets_sale_price(): void
    {
        $product = $this->producto(329);

        $changed = $this->pricing->applyDiscount([$product], 20);

        $this->assertSame(1, $changed);
        $this->assertSame('263.20', $product->fresh()->sale_price);
        $this->assertSame('329.00', $product->fresh()->price);
    }

    public function test_apply_discount_rejects_out_of_range(): void
    {
        $product = $this->producto(100);

        foreach ([0, -5, 95] as $percent) {
            try {
                $this->pricing->applyDiscount([$product], $percent);
                $this->fail("El descuento {$percent} % debió rechazarse.");
            } catch (InvalidArgumentException) {
                $this->assertNull($product->fresh()->sale_price);
            }
        }
    }

    public function test_clear_sale_counts_only_products_with_a_sale(): void
    {
        $con = $this->producto(100, 70);
        $sin = $this->producto(100);

        $changed = $this->pricing->clearSale([$con, $sin]);

        $this->assertSame(1, $changed);
        $this->assertNull($con->fresh()->sale_price);
    }

    public function test_quick_price_action_updates_the_product(): void
    {
        $product = $this->producto(329);

        Livewire::test(ListProducts::class)
            ->callTableAction('precio_rapido', $product, data: ['price' => 289.99, 'sale_price' => null])
            ->assertHasNoTableActionErrors();

        $this->assertSame('289.99', $product->fresh()->price);
    }

    public function test_quick_price_action_rejects_sale_above_price(): void
    {
        $product = $this->producto(329);

        Livewire::test(ListProducts::class)
            ->callTableAction('precio_rapido', $product, data: ['price' => 289.99, 'sale_price' => 300])
            ->assertHasTableActionErrors(['sale_price']);

        $this->assertSame('329.00', $product->fresh()->price);
    }

    public function test_bulk_discount_action_applies_to_selected_products(): void
    {
        $a = $this->producto(100);
        $b = $this->producto(200);
        $fuera = $this->producto(300);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('aplicar_descuento', [$a, $b], data: ['percent' => 10])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('90.00', $a->fresh()->sale_price);
        $this->assertSame('180.00', $b->fresh()->sale_price);
        $this->assertNull($fuera->fresh()->sale_price);
    }

    public function test_bulk_adjust_and_clear_actions(): void
    {
        $product = $this->producto(100, 50);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('ajustar_precio', [$product], data: ['percent' => 20])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('120.00', $product->fresh()->price);
        $this->assertSame('50.00', $product->fresh()->sale_price);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('quitar_oferta', [$product])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull($product->fresh()->sale_price);
    }
}
