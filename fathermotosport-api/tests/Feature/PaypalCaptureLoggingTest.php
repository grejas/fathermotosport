<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Payments\PaypalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Toda captura fallida tiene que quedar en el log. Una captura real falló en
 * producción y no dejó ni una línea, así que no se pudo diagnosticar.
 */
class PaypalCaptureLoggingTest extends TestCase
{
    use RefreshDatabase;

    private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-LOG';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    private function pedidoConPagoPendiente(): array
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
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

        return $order;
    }

    public function test_a_rejected_capture_is_logged_with_the_paypal_response(): void
    {
        $order = $this->pedidoConPagoPendiente();

        // PayPal responde 422 ORDER_NOT_APPROVED (el caso real de producción).
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['issue' => 'ORDER_NOT_APPROVED', 'description' => 'Payer has not yet approved the Order']],
            ], 422),
        ]);

        Log::spy();

        $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER_ID, [], [
            'X-Order-Token' => $order['access_token'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'ORDER_NOT_APPROVED')
            ->assertJsonPath('order_number', $order['order_number']);

        // El servicio registra el cuerpo completo que devolvió PayPal…
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'falló la captura')
                && $ctx['http_status'] === 422
                && str_contains($ctx['respuesta'], 'ORDER_NOT_APPROVED'));

        // …y el controlador deja su propia línea con el pedido afectado.
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'rechazó la captura')
                && $ctx['order'] === $order['id']);
    }

    public function test_a_capture_without_authorization_is_logged(): void
    {
        $this->pedidoConPagoPendiente();

        Log::spy();

        $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER_ID, [], [
            'X-Order-Token' => 'token-incorrecto',
        ])->assertForbidden();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'falta de autorización')
                && $ctx['con_token'] === true
                && $ctx['con_sesion'] === false);
    }

    public function test_a_capture_for_an_unknown_paypal_order_is_logged(): void
    {
        Log::spy();

        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-DESCONOCIDA')->assertStatus(404);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'no existe en payments')
                && $ctx['paypal_order'] === 'PAYPAL-DESCONOCIDA');
    }

    public function test_a_capture_that_is_not_completed_is_logged(): void
    {
        $order = $this->pedidoConPagoPendiente();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            '*/v2/checkout/orders/*/capture' => Http::response(['id' => 'X', 'status' => 'PENDING']),
        ]);

        Log::spy();

        $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER_ID, [], [
            'X-Order-Token' => $order['access_token'],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'PENDING');

        $this->assertSame('pending', Order::findOrFail($order['id'])->payment_status);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'sin estado COMPLETED')
                && $ctx['estado'] === 'PENDING');
    }

    public function test_a_failed_authentication_against_paypal_is_logged(): void
    {
        $order = $this->pedidoConPagoPendiente();

        // Caso credenciales equivocadas (sandbox con PAYPAL_MODE=live o al revés).
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        Log::spy();

        $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER_ID, [], [
            'X-Order-Token' => $order['access_token'],
        ])->assertStatus(422);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $mensaje, array $ctx) => str_contains($mensaje, 'no se pudo autenticar')
                && str_contains($ctx['respuesta'], 'invalid_client'));
    }

    public function test_http_fake_is_not_hiding_a_real_call(): void
    {
        // Red de seguridad del propio test: si el servicio dejara de llamar a PayPal,
        // los tests de arriba pasarían sin verificar nada.
        $order = $this->pedidoConPagoPendiente();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            '*/v2/checkout/orders/*/capture' => Http::response(['status' => 'COMPLETED', 'purchase_units' => [[
                'payments' => ['captures' => [['amount' => ['value' => $order['total']]]]],
            ]]]),
        ]);

        $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER_ID, [], [
            'X-Order-Token' => $order['access_token'],
        ])->assertOk();

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/capture'));
    }
}
