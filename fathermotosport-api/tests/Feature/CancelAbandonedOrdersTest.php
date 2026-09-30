<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Los pedidos que quedan sin pagar se cancelan. Importa por PayPal Express: un clic
 * en el carrito ya crea el pedido, así que los abandonos acumulan pedidos muertos.
 *
 * El stock nunca se toca acá: se descuenta al confirmar el pago, no al crear el
 * pedido, así que un abandono no tiene nada que devolver.
 */
class CancelAbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    /** @return array{0: ProductVariant, 1: Order} */
    private function pedidoSinPagar(int $minutosDeAntiguedad, int $cantidad = 2): array
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 10]);

        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => $cantidad]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->json('order');

        // Se envejece el pedido para que entre en la ventana del comando.
        DB::table('orders')->where('id', $order['id'])
            ->update(['created_at' => now()->subMinutes($minutosDeAntiguedad)]);

        return [$variant->fresh(), Order::findOrFail($order['id'])];
    }

    public function test_creating_the_order_does_not_touch_the_stock(): void
    {
        [$variant] = $this->pedidoSinPagar(90);

        $this->assertSame(10, $variant->stock, 'el stock se descuenta al pagar, no al crear el pedido');
    }

    public function test_dry_run_reports_but_changes_nothing(): void
    {
        [$variant, $order] = $this->pedidoSinPagar(90);

        $this->artisan('orders:cancel-abandoned')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_apply_cancels_the_order_without_moving_stock(): void
    {
        [$variant, $order] = $this->pedidoSinPagar(90);

        $this->artisan('orders:cancel-abandoned --apply')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);

        // Nada que devolver: el stock sigue intacto y no se inventa un movimiento.
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'return']);
    }

    public function test_recent_orders_are_left_alone(): void
    {
        // Sin flag: el umbral por defecto es 30 min, y un pago empezado hace 20 puede
        // estar todavía abierto en la ventana de PayPal.
        [$variant, $order] = $this->pedidoSinPagar(20);

        $this->artisan('orders:cancel-abandoned --apply')->assertSuccessful();

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_default_threshold_is_thirty_minutes(): void
    {
        [, $order] = $this->pedidoSinPagar(40);

        $this->artisan('orders:cancel-abandoned --apply')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_orders_flagged_for_attention_are_never_cancelled(): void
    {
        [, $order] = $this->pedidoSinPagar(180);
        // Caso real: el webhook de Stripe informó un importe distinto al del pedido, así
        // que hay dinero cobrado sin acordar y lo resuelve una persona. Cancelarlo solo
        // dejaría el cobro huérfano.
        $order->update(['attention_reason' => 'Stripe cobró 150.00 pero el pedido totaliza 200.00.']);

        $this->artisan('orders:cancel-abandoned --apply')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_paid_orders_are_never_cancelled(): void
    {
        [, $order] = $this->pedidoSinPagar(180);
        $order->update(['payment_status' => 'paid', 'status' => 'processing']);

        $this->artisan('orders:cancel-abandoned --apply')->assertSuccessful();

        $this->assertSame('processing', $order->fresh()->status);
    }
}
