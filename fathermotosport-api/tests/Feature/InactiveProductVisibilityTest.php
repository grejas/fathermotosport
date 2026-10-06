<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Desactivar un producto en el panel lo saca de la tienda entera: no se lista, no
 * se busca, no tiene página, no se compra y no se ofrece como venta cruzada.
 *
 * Las variantes del inactivo se dejan ACTIVAS a propósito: el caso real es apagar el
 * producto sin tocar sus tallas, y es justo el que se escapaba.
 */
class InactiveProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Category $categoria;

    private Product $activo;

    private Product $inactivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->categoria = Category::create([
            'name' => 'Cascos de prueba',
            'slug' => 'cascos-prueba-'.Str::random(5),
            'is_active' => true,
        ]);

        $comun = [
            'category_id' => $this->categoria->id,
            'price' => 100,
            'sale_price' => null,
            'weight' => 1,
            'is_featured' => true,
            'is_new' => true,
            'is_popular' => true,
        ];

        $this->activo = $this->producto('Casco Zeta Visible', $comun + ['is_active' => true]);
        $this->inactivo = $this->producto('Casco Zeta Apagado', $comun + ['is_active' => false]);
    }

    /** Producto con una sola variante activa y con stock. */
    private function producto(string $nombre, array $atributos): Product
    {
        $product = Product::factory()->create(['name' => $nombre] + $atributos);
        $product->variants()->delete();
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5, 'is_active' => true]);

        return $product->fresh('variants');
    }

    private function idsDe($respuesta, string $ruta = 'data'): array
    {
        return collect($respuesta->json($ruta))->pluck('id')->all();
    }

    public function test_the_catalog_lists_only_active_products(): void
    {
        $ids = $this->idsDe($this->getJson('/api/v1/products?per_page=60')->assertOk());

        $this->assertContains($this->activo->id, $ids);
        $this->assertNotContains($this->inactivo->id, $ids);
    }

    public function test_catalog_filters_do_not_bring_inactive_products_back(): void
    {
        foreach (['is_featured=1', 'is_new=1', 'is_popular=1', "category={$this->categoria->id}"] as $filtro) {
            $ids = $this->idsDe($this->getJson("/api/v1/products?per_page=60&{$filtro}")->assertOk());

            $this->assertNotContains($this->inactivo->id, $ids, "filtro {$filtro}");
        }
    }

    public function test_search_ignores_inactive_products(): void
    {
        $ids = $this->idsDe($this->getJson('/api/v1/products/search?q=Casco Zeta')->assertOk());

        $this->assertSame([$this->activo->id], $ids);
    }

    public function test_the_home_featured_block_ignores_inactive_products(): void
    {
        $ids = $this->idsDe($this->getJson('/api/v1/products/featured')->assertOk());

        $this->assertContains($this->activo->id, $ids);
        $this->assertNotContains($this->inactivo->id, $ids);
    }

    public function test_the_category_page_lists_and_counts_only_active_products(): void
    {
        $respuesta = $this->getJson("/api/v1/categories/{$this->categoria->slug}")->assertOk();
        $this->assertSame([$this->activo->id], $this->idsDe($respuesta, 'products.data'));

        $categoria = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))
            ->firstWhere('id', $this->categoria->id);
        $this->assertSame(1, $categoria['products_count']);
    }

    public function test_related_products_ignore_inactive_ones(): void
    {
        // El bloque "relacionados" del front pide /products por categoría, 4 por página.
        $ids = $this->idsDe(
            $this->getJson("/api/v1/products?category={$this->categoria->id}&per_page=4")->assertOk()
        );

        $this->assertNotContains($this->inactivo->id, $ids);
    }

    public function test_the_inactive_product_page_is_a_404(): void
    {
        $this->getJson("/api/v1/products/{$this->inactivo->slug}")->assertNotFound();
        $this->getJson("/api/v1/products/{$this->activo->slug}")->assertOk();
    }

    public function test_an_inactive_product_cannot_be_added_to_the_cart(): void
    {
        $this->postJson('/api/v1/cart/items', [
            'variant_id' => $this->inactivo->variants->first()->id,
            'quantity' => 1,
        ], ['X-Session-Id' => 'sesion-prueba'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant_id');

        $this->postJson('/api/v1/cart/items', [
            'variant_id' => $this->activo->variants->first()->id,
            'quantity' => 1,
        ], ['X-Session-Id' => 'sesion-prueba'])->assertOk();
    }

    public function test_an_inactive_product_cannot_be_ordered(): void
    {
        $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $this->inactivo->variants->first()->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_upsell_does_not_offer_or_sell_an_inactive_product(): void
    {
        $regla = UpsellRule::create([
            'trigger_product_id' => $this->activo->id,
            'offer_product_id' => $this->inactivo->id,
            'discount_percent' => 20,
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::findOrFail($this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $this->activo->variants->first()->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order.id'));
        $order->update(['payment_status' => 'paid', 'status' => 'processing']);
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $cabeceras)
            ->assertOk()
            ->assertJsonCount(0, 'offers');

        // Ni armando el pedido a mano con la regla y la talla.
        $this->postJson("/api/v1/orders/{$order->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $this->inactivo->variants->first()->id]],
        ], $cabeceras)->assertStatus(422)->assertJsonValidationErrors('items.0.rule_id');
    }
}
