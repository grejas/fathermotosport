<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Payments\PaypalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Solo PAYMENT.CAPTURE.COMPLETED confirma el cobro. El resto de eventos se responden
 * con 200 pero no tocan el pedido.
 */
class PaypalWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        // La verificación de firma llama a la API de PayPal: se simula como válida.
        $this->mock(PaypalService::class, fn ($mock) => $mock->shouldReceive('verifyWebhook')->andReturn(true));
    }

    private function pedidoPendiente(): Order
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->json('order');

        Payment::create([
            'order_id' => $order['id'],
            'provider' => 'paypal',
            'transaction_id' => self::PAYPAL_ORDER_ID,
            'currency' => 'USD',
            'amount' => $order['total'],
            'status' => 'pending',
        ]);

        return Order::findOrFail($order['id']);
    }

    public function test_order_approved_does_not_mark_the_order_as_paid(): void
    {
        $order = $this->pedidoPendiente();

        $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => self::PAYPAL_ORDER_ID],
        ])
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('handled', false);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', Payment::where('transaction_id', self::PAYPAL_ORDER_ID)->value('status'));
    }

    public function test_capture_completed_marks_the_order_as_paid(): void
    {
        $order = $this->pedidoPendiente();

        $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                // resource.id es el id de la CAPTURA; el de la orden viene aparte.
                'id' => 'CAPTURE-999',
                'supplementary_data' => ['related_ids' => ['order_id' => self::PAYPAL_ORDER_ID]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('approved', Payment::where('transaction_id', self::PAYPAL_ORDER_ID)->value('status'));
    }

    public function test_other_events_are_acknowledged_without_touching_the_order(): void
    {
        $order = $this->pedidoPendiente();

        foreach (['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.REFUNDED', 'CHECKOUT.ORDER.COMPLETED'] as $evento) {
            $this->postJson('/api/v1/webhooks/paypal', [
                'event_type' => $evento,
                'resource' => ['id' => self::PAYPAL_ORDER_ID],
            ])
                ->assertOk()
                ->assertJsonPath('handled', false);
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_capture_for_an_unknown_payment_is_acknowledged_without_error(): void
    {
        $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'PAYPAL-ORDER-DESCONOCIDA'],
        ])
            ->assertOk()
            ->assertJsonPath('handled', false);
    }
}
