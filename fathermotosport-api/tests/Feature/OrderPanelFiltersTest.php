<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El listado de pedidos del panel esconde los cancelados por defecto.
 *
 * Los abandonos de PayPal se acumulan como pedidos cancelados (un clic en el carrito
 * ya crea el pedido) y tapaban lo que sí hay que atender. Siguen en la base: solo
 * dejaron de aparecer en la vista general.
 */
class OrderPanelFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function pedido(string $status, array $extra = []): Order
    {
        return Order::create(array_merge([
            'status' => $status,
            'subtotal' => 100,
            'discount' => 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'payment_status' => $status === 'cancelled' ? 'pending' : 'paid',
            'shipping_status' => 'pending',
            'payment_method' => 'paypal',
            'country' => 'Bolivia',
            'guest_email' => 'invitado@example.com',
        ], $extra));
    }

    public function test_cancelled_orders_are_hidden_by_default(): void
    {
        $vivo = $this->pedido('processing');
        $cancelado = $this->pedido('cancelled');

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$vivo])
            ->assertCanNotSeeTableRecords([$cancelado]);

        // No se borran: siguen en la base para consultarlos.
        $this->assertDatabaseHas('orders', ['id' => $cancelado->id, 'status' => 'cancelled']);
    }

    public function test_the_filter_brings_the_cancelled_orders_back(): void
    {
        $vivo = $this->pedido('processing');
        $cancelado = $this->pedido('cancelled');

        Livewire::test(ListOrders::class)
            ->filterTable('cancelados', true)
            ->assertCanSeeTableRecords([$vivo, $cancelado]);

        Livewire::test(ListOrders::class)
            ->filterTable('cancelados', false)
            ->assertCanSeeTableRecords([$cancelado])
            ->assertCanNotSeeTableRecords([$vivo]);
    }

    public function test_orders_needing_attention_are_listed_first(): void
    {
        // El más nuevo no requiere atención: sin la regla de orden, iría arriba.
        $atencion = $this->pedido('processing', [
            'attention_reason' => 'Pagado sin stock suficiente',
            'created_at' => now()->subDay(),
        ]);
        $normal = $this->pedido('processing', ['created_at' => now()]);

        $ids = Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$atencion, $normal])
            ->instance()->getTableRecords()->pluck('id')->all();

        $this->assertSame([$atencion->id, $normal->id], $ids);
    }
}
