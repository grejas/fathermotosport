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
        $this->postJson('/api/v1/payments/paypal/create', ['order_id' => $order['id']])->assertForbidden();
    }

    public function test_paypal_create_requires_authorization_and_refuses_paid_orders(): void
    {
        $order = $this->postJson('/api/v1/orders', $this->payload())->json('order');

        // Sin token no se puede iniciar el pago.
        $this->postJson('/api/v1/payments/paypal/create', ['order_id' => $order['id']])->assertForbidden();

        // Ya pagado: 409 antes de llamar a PayPal (evita el cobro doble).
        Order::whereKey($order['id'])->update(['payment_status' => 'paid']);
        $this->postJson('/api/v1/payments/paypal/create', ['order_id' => $order['id']], [
            'X-Order-Token' => $order['access_token'],
        ])->assertStatus(409);
    }

    public function test_capture_is_idempotent_when_the_order_is_already_paid(): void
    {
        $order = $this->postJson('/api/v1/orders', $this->payload())->json('order');

        Payment::create([
            'order_id' => $order['id'],
            'provider' => 'paypal',
            'transaction_id' => 'PAYPAL-TEST-123',
            'currency' => 'USD',
            'amount' => $order['total'],
            'status' => 'approved',
        ]);
        Order::whereKey($order['id'])->update(['payment_status' => 'paid']);

        // No llama a PayPal: responde con el estado guardado.
        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-TEST-123', [], [
            'X-Order-Token' => $order['access_token'],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETED')
            ->assertJsonPath('order.payment_status', 'paid')
            // La respuesta no expone datos personales del pedido.
            ->assertJsonMissingPath('order.guest_email')
            ->assertJsonMissingPath('order.address');
    }

    public function test_capture_rejects_a_token_that_does_not_match(): void
    {
        $order = $this->postJson('/api/v1/orders', $this->payload())->json('order');

        Payment::create([
            'order_id' => $order['id'],
            'provider' => 'paypal',
            'transaction_id' => 'PAYPAL-TEST-456',
            'currency' => 'USD',
            'amount' => $order['total'],
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-TEST-456', [], [
            'X-Order-Token' => 'token-incorrecto',
        ])->assertForbidden();
    }

    /** La misma regla de acceso, a nivel de modelo. */
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
