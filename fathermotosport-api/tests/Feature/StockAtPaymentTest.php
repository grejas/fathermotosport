<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El stock se descuenta cuando el pago se confirma, no cuando se crea el pedido.
 *
 * Antes se reservaba al crear, y cada cliente que abandonaba el pago dejaba unidades
 * bloqueadas. La contrapartida es que dos compradores pueden llegar al pago con la
 * última unidad: eso se cobra igual y se marca para revisión manual.
 */
class StockAtPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    private function variante(int $stock = 5): ProductVariant
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => $stock]);
    }

    /** Crea un pedido de 2 unidades por el checkout normal y le deja un pago pendiente. */
    private function pedidoConPagoPendiente(ProductVariant $variant): Order
    {
        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->assertCreated()->json('order');

        Payment::create([
            'order_id' => $order['id'],
            'provider' => 'paypal',
            'transaction_id' => 'PAYPAL-CAPTURE-1',
            'currency' => 'USD',
            'amount' => $order['total'],
            'status' => 'pending',
        ]);

        return Order::findOrFail($order['id']);
    }

    private function fingirCapturaExitosa(Order $order): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-CAPTURE-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [['amount' => ['value' => (string) $order->total]]]],
                ]],
            ]),
        ]);
    }

    private function capturar(Order $order): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-CAPTURE-1', [], [
            'X-Order-Token' => $order->access_token,
        ]);
    }

    public function test_the_stock_drops_only_when_the_payment_is_confirmed(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedidoConPagoPendiente($variant);

        $this->assertSame(5, $variant->fresh()->stock, 'crear el pedido no reserva unidades');

        $this->fingirCapturaExitosa($order);
        $this->capturar($order)->assertOk();

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->attention_reason);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $variant->product_id,
            'type' => 'sale',
            'quantity' => -2,
            'reason' => "Venta - pedido {$order->order_number}",
        ]);
    }

    public function test_a_second_confirmation_does_not_discount_the_stock_twice(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedidoConPagoPendiente($variant);

        $this->fingirCapturaExitosa($order);
        $this->capturar($order)->assertOk();
        $this->capturar($order)->assertOk();

        $this->assertSame(3, $variant->fresh()->stock, 'el webhook y el retorno del cliente pueden llegar los dos');
        $this->assertSame(1, \App\Models\InventoryMovement::where('type', 'sale')->count());
    }

    public function test_paying_without_enough_stock_is_flagged_instead_of_failing(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedidoConPagoPendiente($variant);

        // Otro comprador se llevó las unidades entre la creación del pedido y el pago.
        $variant->update(['stock' => 1]);

        Log::spy();
        $this->fingirCapturaExitosa($order);

        // El cliente no ve un error: su dinero ya se cobró.
        $this->capturar($order)->assertOk();

        $fresco = $order->fresh();
        $this->assertSame('paid', $fresco->payment_status);
        $this->assertStringContainsString('Pagado sin stock suficiente', (string) $fresco->attention_reason);
        $this->assertStringContainsString('pedidas 2, disponibles 1', (string) $fresco->attention_reason);
        $this->assertStringContainsString('⚠️', (string) $fresco->notes);

        // Se descuenta lo que había: el stock no queda negativo.
        $this->assertSame(0, $variant->fresh()->stock);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $mensaje) => str_contains($mensaje, 'requiere atención manual'))
            ->once();
    }
}
