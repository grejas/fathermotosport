<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\UpsellService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Venta cruzada post-compra.
 *
 * El riesgo acá no es la UI: es que alguien se fabrique un pedido con un descuento
 * inventado. El precio y el porcentaje NUNCA vienen del cliente, solo referencias a la
 * regla, y estos tests cubren cada forma de intentar torcer eso.
 */
class UpsellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    /**
     * Producto con UNA sola variante controlada.
     *
     * ProductFactory agrega 2-4 variantes aleatorias por su cuenta, y acá importa el
     * stock exacto: si quedan variantes extra con stock, un producto "agotado" seguiría
     * siendo ofrecible y el conteo de tallas no sería el esperado.
     */
    private function producto(float $precio = 100, int $stock = 5, ?string $talla = 'M'): ProductVariant
    {
        $product = Product::factory()->create(['price' => $precio, 'sale_price' => null, 'weight' => 1]);
        $product->variants()->delete();

        return ProductVariant::factory()->for($product)->create(['size' => $talla, 'stock' => $stock]);
    }

    private function regla(ProductVariant $disparador, ProductVariant $ofrecido, array $extra = []): UpsellRule
    {
        return UpsellRule::create(array_merge([
            'trigger_product_id' => $disparador->product_id,
            'offer_product_id' => $ofrecido->product_id,
            'discount_percent' => 20,
            'priority' => 0,
            'is_active' => true,
        ], $extra));
    }

    /** Pedido de invitado ya pagado, que es la precondición de toda la función. */
    private function pedidoPagado(ProductVariant $variant): Order
    {
        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order');

        $modelo = Order::findOrFail($order['id']);
        $modelo->update(['payment_status' => 'paid', 'status' => 'processing']);

        return $modelo->fresh();
    }

    private function cabeceras(Order $order): array
    {
        return ['X-Order-Token' => $order->access_token];
    }

    // ───────────────────────── GET ─────────────────────────

    public function test_the_offers_require_the_order_token(): void
    {
        $order = $this->pedidoPagado($this->producto());

        $this->getJson("/api/v1/orders/{$order->id}/upsell")->assertStatus(403);
    }

    public function test_a_logged_in_user_cannot_see_someone_elses_offers(): void
    {
        $order = $this->pedidoPagado($this->producto());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/orders/{$order->id}/upsell")->assertStatus(403);
    }

    public function test_an_unpaid_order_gets_no_offers(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $this->regla($casco, $visera);

        $order = $this->pedidoPagado($casco);
        $order->update(['payment_status' => 'pending', 'status' => 'pending']);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    public function test_the_offer_carries_the_price_already_discounted(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $this->regla($casco, $visera, ['discount_percent' => 25]);

        $order = $this->pedidoPagado($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.discount_percent', 25)
            ->assertJsonPath('offers.0.price', '80.00')
            ->assertJsonPath('offers.0.discounted_price', '60.00')
            ->assertJsonPath('offers.0.product.id', $visera->product_id)
            ->assertJsonCount(1, 'offers.0.product.variants');
    }

    public function test_offers_are_capped_and_ordered_by_priority(): void
    {
        $casco = $this->producto();
        $order = $this->pedidoPagado($casco);

        // Cuatro candidatas para tres lugares.
        $esperadas = [];
        foreach ([5, 30, 20, 10] as $prioridad) {
            $ofrecido = $this->producto(50, 2, null);
            $this->regla($casco, $ofrecido, ['priority' => $prioridad]);
            $esperadas[$prioridad] = $ofrecido->product_id;
        }

        $respuesta = $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonCount(UpsellService::MAX_OFERTAS, 'offers');

        $ids = collect($respuesta->json('offers'))->pluck('product.id')->all();
        $this->assertSame([$esperadas[30], $esperadas[20], $esperadas[10]], $ids);
    }

    public function test_a_product_offered_by_two_rules_appears_once_with_the_best_discount(): void
    {
        $casco = $this->producto();
        $guantes = $this->producto(60);
        $balaclava = $this->producto(35, 4);

        // El cliente compra casco y guantes; los dos ofrecen la misma balaclava.
        $this->regla($casco, $balaclava, ['discount_percent' => 20]);
        $this->regla($guantes, $balaclava, ['discount_percent' => 15]);

        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [
                ['variant_id' => $casco->id, 'quantity' => 1],
                ['variant_id' => $guantes->id, 'quantity' => 1],
            ],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order');

        $modelo = Order::findOrFail($order['id']);
        $modelo->update(['payment_status' => 'paid']);

        $this->getJson("/api/v1/orders/{$modelo->id}/upsell", $this->cabeceras($modelo->fresh()))
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            // Gana el descuento más alto: mostrar el peor existiendo uno mejor sería
            // indefendible si el cliente compara.
            ->assertJsonPath('offers.0.discount_percent', 20);
    }

    public function test_a_product_without_stock_is_not_offered(): void
    {
        $casco = $this->producto();
        $agotado = $this->producto(80, 0, null);
        $this->regla($casco, $agotado);

        $order = $this->pedidoPagado($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    public function test_an_inactive_rule_is_not_offered(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $this->regla($casco, $visera, ['is_active' => false]);

        $order = $this->pedidoPagado($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    public function test_an_upsell_order_does_not_generate_more_offers(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $balaclava = $this->producto(35, 4);
        $regla = $this->regla($casco, $visera);
        // La visera dispararía otra oferta: sin el corte se encadenarían sin fin.
        $this->regla($visera, $balaclava);

        $original = $this->pedidoPagado($casco);
        $id = $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))->assertCreated()->json('order.id');

        $upsell = Order::findOrFail($id);
        $upsell->update(['payment_status' => 'paid']);

        $this->getJson("/api/v1/orders/{$upsell->id}/upsell", $this->cabeceras($upsell->fresh()))
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    // ───────────────────────── POST ─────────────────────────

    public function test_it_creates_a_separate_order_with_free_shipping_and_the_discount(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $balaclava = $this->producto(35, 4);
        $r1 = $this->regla($casco, $visera, ['discount_percent' => 25]);
        $r2 = $this->regla($casco, $balaclava, ['discount_percent' => 20]);

        $original = $this->pedidoPagado($casco);
        $tallaBalaclava = $balaclava->id;

        $respuesta = $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [
                ['rule_id' => $r1->id, 'variant_id' => $visera->id],
                ['rule_id' => $r2->id, 'variant_id' => $tallaBalaclava],
            ],
        ], $this->cabeceras($original))->assertCreated();

        $upsell = Order::with('items')->findOrFail($respuesta->json('order.id'));

        // Pedido aparte, vinculado al original y con la MISMA dirección (no se duplica).
        $this->assertSame($original->id, $upsell->upsell_of_order_id);
        $this->assertSame($original->address_id, $upsell->address_id);
        $this->assertSame($original->guest_email, $upsell->guest_email);
        $this->assertTrue($upsell->esUpsell());

        // Precio completo en los items, el ahorro en discount: 115 - 27 = 88.
        $this->assertSame('115.00', (string) $upsell->subtotal);
        $this->assertSame('27.00', (string) $upsell->discount);
        $this->assertSame('0.00', (string) $upsell->shipping, 'viaja con el original');
        $this->assertSame('88.00', (string) $upsell->total);
        $this->assertCount(2, $upsell->items);
        $this->assertSame('pending', $upsell->payment_status);
    }

    public function test_the_stock_is_not_touched_until_the_upsell_is_paid(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))->assertCreated();

        $this->assertSame(3, $visera->fresh()->stock);
    }

    public function test_choosing_again_reuses_the_pending_order_instead_of_duplicating_it(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $balaclava = $this->producto(35, 4);
        $r1 = $this->regla($casco, $visera);
        $r2 = $this->regla($casco, $balaclava);
        $original = $this->pedidoPagado($casco);

        $primero = $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $r1->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))->assertCreated()->json('order.id');

        // Cambia de opinión y elige otra cosa.
        $segundo = $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $r2->id, 'variant_id' => $balaclava->id]],
        ], $this->cabeceras($original))->assertCreated()->json('order.id');

        $this->assertSame($primero, $segundo, 'es el mismo pedido, no uno nuevo');
        $this->assertSame(1, Order::where('upsell_of_order_id', $original->id)->count());

        $upsell = Order::with('items.variant')->findOrFail($segundo);
        $this->assertCount(1, $upsell->items, 'los items viejos se reemplazaron');
        $this->assertSame($balaclava->id, $upsell->items->first()->product_variant_id);
        $this->assertSame('28.00', (string) $upsell->total);
    }

    public function test_once_the_upsell_is_paid_no_more_offers_are_shown(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera);
        $original = $this->pedidoPagado($casco);

        $id = $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))->assertCreated()->json('order.id');

        Order::findOrFail($id)->update(['payment_status' => 'paid']);

        $this->getJson("/api/v1/orders/{$original->id}/upsell", $this->cabeceras($original))
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    // ───────── Intentos de torcer el precio o la regla ─────────

    public function test_a_rule_whose_trigger_is_not_in_the_order_is_refused(): void
    {
        $casco = $this->producto();
        $otroCasco = $this->producto(200);
        $visera = $this->producto(80, 3, null);
        // La regla se dispara por un producto que este pedido NO contiene.
        $regla = $this->regla($otroCasco, $visera);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.rule_id');

        $this->assertSame(0, Order::where('upsell_of_order_id', $original->id)->count());
    }

    public function test_an_inactive_rule_cannot_be_used(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera, ['is_active' => false]);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.rule_id');
    }

    public function test_a_variant_of_another_product_cannot_be_smuggled_in(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $caro = $this->producto(900, 5, 'L');
        $regla = $this->regla($casco, $visera);
        $original = $this->pedidoPagado($casco);

        // Con la regla de la visera (25% off) pero mandando la variante de un casco de $900.
        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $caro->id]],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.variant_id');
    }

    public function test_a_variant_without_stock_is_refused(): void
    {
        $casco = $this->producto();
        $agotado = $this->producto(80, 0, null);
        $regla = $this->regla($casco, $agotado);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $agotado->id]],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.variant_id');
    }

    public function test_the_same_product_cannot_be_selected_twice(): void
    {
        $casco = $this->producto();
        $guantes = $this->producto(60);
        $balaclava = $this->producto(35, 4);
        $r1 = $this->regla($casco, $balaclava, ['discount_percent' => 20]);
        $r2 = $this->regla($guantes, $balaclava, ['discount_percent' => 15]);

        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [
                ['variant_id' => $casco->id, 'quantity' => 1],
                ['variant_id' => $guantes->id, 'quantity' => 1],
            ],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order');

        $original = Order::findOrFail($order['id']);
        $original->update(['payment_status' => 'paid']);
        $original = $original->fresh();

        // Dos reglas distintas que ofrecen el mismo producto: duplicaría la compra.
        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [
                ['rule_id' => $r1->id, 'variant_id' => $balaclava->id],
                ['rule_id' => $r2->id, 'variant_id' => $balaclava->id],
            ],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.1.rule_id');
    }

    public function test_a_product_already_in_the_order_is_not_offered_again(): void
    {
        $casco = $this->producto();
        // Regla que se ofrece a sí misma a través de otro disparador presente.
        $regla = $this->regla($casco, $casco, ['discount_percent' => 50]);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $casco->id]],
        ], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.rule_id');
    }

    public function test_an_unpaid_order_cannot_generate_an_upsell(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera);
        $original = $this->pedidoPagado($casco);
        $original->update(['payment_status' => 'pending', 'status' => 'pending']);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ], $this->cabeceras($original->fresh()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('order');
    }

    public function test_more_than_the_maximum_is_refused(): void
    {
        $casco = $this->producto();
        $original = $this->pedidoPagado($casco);

        $items = [];
        for ($i = 0; $i <= UpsellService::MAX_OFERTAS; $i++) {
            $ofrecido = $this->producto(50, 2, null);
            $items[] = [
                'rule_id' => $this->regla($casco, $ofrecido)->id,
                'variant_id' => $ofrecido->id,
            ];
        }

        $this->postJson("/api/v1/orders/{$original->id}/upsell", ['items' => $items], $this->cabeceras($original))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_the_upsell_cannot_be_created_without_the_token(): void
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera);
        $original = $this->pedidoPagado($casco);

        $this->postJson("/api/v1/orders/{$original->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $visera->id]],
        ])->assertStatus(403);

        $this->assertSame(0, Order::where('upsell_of_order_id', $original->id)->count());
    }

    // ─────────────── Ventana: la oferta existe solo en el modal ───────────────

    /** Pedido pagado con una oferta disponible, listo para probar la ventana. */
    private function conOferta(): array
    {
        $casco = $this->producto();
        $visera = $this->producto(80, 3, null);
        $regla = $this->regla($casco, $visera);

        return [$this->pedidoPagado($casco), $regla, $visera];
    }

    private function verOfertas(Order $order)
    {
        return $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order));
    }

    private function comprarOferta(Order $order, UpsellRule $regla, ProductVariant $variante)
    {
        return $this->postJson("/api/v1/orders/{$order->id}/upsell", [
            'items' => [['rule_id' => $regla->id, 'variant_id' => $variante->id]],
        ], $this->cabeceras($order));
    }

    private function descartarOferta(Order $order, ?array $cabeceras = null)
    {
        return $this->postJson("/api/v1/orders/{$order->id}/upsell/dismiss", [], $cabeceras ?? $this->cabeceras($order));
    }

    public function test_the_first_time_offers_are_shown_the_window_starts(): void
    {
        [$order] = $this->conOferta();
        $this->assertNull($order->upsell_offered_at);

        $this->verOfertas($order)->assertOk()->assertJsonCount(1, 'offers');

        $this->assertNotNull($order->fresh()->upsell_offered_at);
    }

    public function test_reloading_within_the_window_still_shows_the_offer_without_restarting_it(): void
    {
        [$order] = $this->conOferta();
        $this->verOfertas($order)->assertJsonCount(1, 'offers');
        $primera = $order->fresh()->upsell_offered_at;

        $this->travel(UpsellService::VENTANA_MINUTOS - 1)->minutes();

        $this->verOfertas($order)->assertOk()->assertJsonCount(1, 'offers');
        // Recargar no corre el plazo: sigue contando desde la primera vez.
        $this->assertEquals($primera, $order->fresh()->upsell_offered_at);
    }

    public function test_the_offer_expires_after_the_window(): void
    {
        [$order] = $this->conOferta();
        $this->verOfertas($order)->assertJsonCount(1, 'offers');

        $this->travel(UpsellService::VENTANA_MINUTOS + 1)->minutes();

        $this->verOfertas($order)->assertOk()->assertJsonCount(0, 'offers');
    }

    public function test_an_order_without_offers_does_not_start_the_window(): void
    {
        $order = $this->pedidoPagado($this->producto());

        $this->verOfertas($order)->assertOk()->assertJsonCount(0, 'offers');

        $this->assertNull($order->fresh()->upsell_offered_at);
    }

    public function test_a_dismissed_offer_is_not_shown_again(): void
    {
        [$order] = $this->conOferta();
        $this->verOfertas($order)->assertJsonCount(1, 'offers');

        $this->descartarOferta($order)->assertOk()->assertJsonPath('dismissed', true);

        $this->assertNotNull($order->fresh()->upsell_dismissed_at);
        $this->verOfertas($order)->assertOk()->assertJsonCount(0, 'offers');
    }

    public function test_dismissing_twice_keeps_the_first_date(): void
    {
        [$order] = $this->conOferta();
        $this->descartarOferta($order)->assertOk();
        $primera = $order->fresh()->upsell_dismissed_at;

        $this->travel(5)->minutes();
        $this->descartarOferta($order)->assertOk();

        $this->assertEquals($primera, $order->fresh()->upsell_dismissed_at);
    }

    public function test_cannot_buy_after_dismissing(): void
    {
        [$order, $regla, $visera] = $this->conOferta();
        $this->verOfertas($order);
        $this->descartarOferta($order)->assertOk();

        $this->comprarOferta($order, $regla, $visera)
            ->assertStatus(409)
            ->assertJsonPath('message', UpsellService::MENSAJE_OFERTA_CERRADA);

        $this->assertSame(0, Order::where('upsell_of_order_id', $order->id)->count());
    }

    public function test_cannot_buy_after_the_window_expires(): void
    {
        [$order, $regla, $visera] = $this->conOferta();
        $this->verOfertas($order);

        $this->travel(UpsellService::VENTANA_MINUTOS + 1)->minutes();

        $this->comprarOferta($order, $regla, $visera)
            ->assertStatus(409)
            ->assertJsonPath('message', UpsellService::MENSAJE_OFERTA_CERRADA);

        $this->assertSame(0, Order::where('upsell_of_order_id', $order->id)->count());
    }

    public function test_buying_within_the_window_works_and_reuses_the_pending_order(): void
    {
        [$order, $regla, $visera] = $this->conOferta();
        $this->verOfertas($order);
        $this->travel(UpsellService::VENTANA_MINUTOS - 1)->minutes();

        $primero = $this->comprarOferta($order, $regla, $visera)->assertCreated()->json('order.id');
        $segundo = $this->comprarOferta($order, $regla, $visera)->assertCreated()->json('order.id');

        $this->assertSame($primero, $segundo);
    }

    public function test_dismissing_cancels_the_unpaid_upsell_order(): void
    {
        [$order, $regla, $visera] = $this->conOferta();
        $upsellId = $this->comprarOferta($order, $regla, $visera)->assertCreated()->json('order.id');

        $this->descartarOferta($order)->assertOk();

        // Ya no se puede cobrar por otro camino una oferta que no existe.
        $this->assertSame('cancelled', Order::findOrFail($upsellId)->status);
    }

    public function test_dismissing_requires_a_valid_token(): void
    {
        [$order] = $this->conOferta();

        $this->descartarOferta($order, [])->assertStatus(403);
        $this->descartarOferta($order, ['X-Order-Token' => 'token-incorrecto'])->assertStatus(403);
        $this->getJson("/api/v1/orders/{$order->id}/upsell", ['X-Order-Token' => 'token-incorrecto'])
            ->assertStatus(403);

        $this->assertNull($order->fresh()->upsell_dismissed_at);
    }

    public function test_an_unpaid_order_neither_shows_nor_starts_the_window(): void
    {
        [$order, $regla, $visera] = $this->conOferta();
        $order->update(['payment_status' => 'pending', 'status' => 'pending']);

        $this->verOfertas($order)->assertOk()->assertJsonCount(0, 'offers');
        $this->assertNull($order->fresh()->upsell_offered_at);

        $this->comprarOferta($order, $regla, $visera)->assertStatus(422);
    }
}
