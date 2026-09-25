<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Acceso a un pedido: por token (comprador invitado) o por sesión (dueño / staff).
 * Conocer el UUID ya no alcanza.
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        $product = Product::factory()->create(['weight' => 1, 'price' => 100, 'sale_price' => null]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        return array_merge([
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba',
                'phone' => '76543210',
                'country' => 'Bolivia',
                'city' => 'Santa Cruz',
                'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ], $overrides);
    }

    public function test_guest_order_returns_a_token_and_only_that_token_opens_it(): void
    {
        $order = $this->postJson('/api/v1/orders', $this->payload())
            ->assertCreated()
            ->json('order');

        $this->assertNotEmpty($order['access_token']);
        $this->assertNull($order['user_id']);

        // Conocer el UUID ya no alcanza.
        $this->getJson("/api/v1/orders/{$order['id']}")->assertForbidden();
        $this->getJson("/api/v1/orders/{$order['id']}?token=token-incorrecto")->assertForbidden();

        // Con el token, por query o por cabecera.
        $this->getJson("/api/v1/orders/{$order['id']}?token={$order['access_token']}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order['order_number']);

        $this->getJson("/api/v1/orders/{$order['id']}", ['X-Order-Token' => $order['access_token']])
            ->assertOk();
    }

    public function test_logged_in_customer_order_is_linked_to_the_account_and_readable_without_token(): void
    {
        $cliente = User::factory()->create();
        Sanctum::actingAs($cliente);

        // Sin guest_email: el email sale de la cuenta.
        $order = $this->postJson('/api/v1/orders', $this->payload(['guest_email' => null]))
            ->assertCreated()
            ->json('order');

        $this->assertSame($cliente->id, $order['user_id']);
        $this->getJson("/api/v1/orders/{$order['id']}")->assertOk();
    }

    public function test_another_customer_cannot_open_someone_elses_order(): void
    {
        $dueno = User::factory()->create();
        Sanctum::actingAs($dueno);
        $order = $this->postJson('/api/v1/orders', $this->payload(['guest_email' => null]))->json('order');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/orders/{$order['id']}")->assertForbidden();
    }

    /**
     * La misma regla que protege el pago (PaymentController), probada sobre el modelo:
     * las rutas de pago están apagadas, así que no se puede verificar por HTTP todavía.
     */
    public function test_order_access_rule_accepts_token_owner_and_staff_only(): void
    {
        $dueno = User::factory()->create();
        Sanctum::actingAs($dueno);
        $order = Order::findOrFail(
            $this->postJson('/api/v1/orders', $this->payload(['guest_email' => null]))->json('order.id')
        );

        $admin = User::where('email', 'admin@fathermotosport.com')->firstOrFail();
        $otro = User::factory()->create();

        // Token correcto, sin sesión.
        $this->assertTrue($order->isAccessibleBy(null, $order->access_token));
        // Token incorrecto o ausente.
        $this->assertFalse($order->isAccessibleBy(null, 'token-incorrecto'));
        $this->assertFalse($order->isAccessibleBy(null, null));
        // Por sesión: el dueño y el staff sí; otro cliente no.
        $this->assertTrue($order->isAccessibleBy($dueno, null));
        $this->assertTrue($order->isAccessibleBy($admin, null));
        $this->assertFalse($order->isAccessibleBy($otro, null));
    }

    public function test_guest_order_token_is_unique_per_order(): void
    {
        $uno = $this->postJson('/api/v1/orders', $this->payload())->json('order.access_token');
        $dos = $this->postJson('/api/v1/orders', $this->payload())->json('order.access_token');

        $this->assertNotSame($uno, $dos);
        $this->assertSame(48, strlen($uno));
    }
}
