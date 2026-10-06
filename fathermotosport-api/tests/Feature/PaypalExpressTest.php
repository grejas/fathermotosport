<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Mail\OrderReceivedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PayPal Express: el pedido se crea desde el carrito sin datos del comprador y se
 * completa con lo que informa PayPal al capturar el pago.
 */
class PaypalExpressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    private function variante(): ProductVariant
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);
    }

    private function opcionDeEnvio(array $attributes = []): ShippingOption
    {
        return ShippingOption::create(array_merge([
            'country_code' => 'BO',
            'method_name' => 'Express DHL',
            'min_weight_kg' => 0,
            'max_weight_kg' => null,
            'price' => 25,
            'currency' => 'USD',
            'estimated_days_min' => 5,
            'estimated_days_max' => 7,
            'is_active' => true,
        ], $attributes));
    }

    private function crearPedidoExpress(string $email = 'escrito@example.com'): array
    {
        $variant = $this->variante();
        $opcion = $this->opcionDeEnvio();

        $order = $this->postJson('/api/v1/orders/paypal-express', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'shipping_country_code' => 'BO',
            'shipping_option_id' => $opcion->id,
            'email' => $email,
        ])->assertCreated()->json('order');

        Payment::create([
            'order_id' => $order['id'],
            'provider' => 'paypal',
            'transaction_id' => 'PAYPAL-EXPRESS-1',
            'currency' => 'USD',
            'amount' => $order['total'],
            'status' => 'pending',
        ]);

        return $order;
    }

    /** Respuesta de captura de PayPal con comprador y dirección, como la real. */
    private function respuestaDeCaptura(string $total, string $paisIso = 'BO'): array
    {
        return [
            'id' => 'PAYPAL-EXPRESS-1',
            'status' => 'COMPLETED',
            'payer' => [
                'name' => ['given_name' => 'Ana', 'surname' => 'Pérez'],
                'email_address' => 'ana.perez@example.com',
                'phone' => ['phone_number' => ['national_number' => '76543210']],
            ],
            'purchase_units' => [[
                'shipping' => [
                    'name' => ['full_name' => 'Ana Pérez'],
                    'address' => [
                        'address_line_1' => 'Av. Siempre Viva 742',
                        'admin_area_2' => 'Santa Cruz',
                        'admin_area_1' => 'Santa Cruz',
                        'postal_code' => '0000',
                        'country_code' => $paisIso,
                    ],
                ],
                'payments' => ['captures' => [['amount' => ['value' => $total]]]],
            ]],
        ];
    }

    public function test_express_order_is_created_with_only_the_notification_email(): void
    {
        $order = $this->crearPedidoExpress();

        $modelo = Order::with('address')->findOrFail($order['id']);
        $this->assertSame('paypal', $modelo->payment_method);
        $this->assertSame('pending', $modelo->payment_status);
        // 2 × 100 + 25 de envío.
        $this->assertSame('225.00', $modelo->total);
        $this->assertSame('Express DHL', $modelo->shipping_method_name);
        $this->assertSame(Order::DATO_PENDIENTE, $modelo->address->address_line);
        $this->assertTrue($modelo->esperaDatosDePaypal());
        // El escrito queda para avisarle; el verificado lo informa PayPal al volver.
        $this->assertSame('escrito@example.com', $modelo->notification_email);
        $this->assertNull($modelo->guest_email);
        $this->assertNull($modelo->email_verificado_por);

        // Nada de correos: todavía no hay pago.
        Mail::assertNothingSent();
    }

    public function test_capture_fills_in_the_buyer_data_and_then_confirms_the_payment(): void
    {
        $order = $this->crearPedidoExpress();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            '*/v2/checkout/orders/*/capture' => Http::response($this->respuestaDeCaptura($order['total'])),
        ]);

        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-EXPRESS-1', [], [
            'X-Order-Token' => $order['access_token'],
        ])->assertOk()->assertJsonPath('status', 'COMPLETED');

        $modelo = Order::with('address')->findOrFail($order['id']);
        $this->assertSame('paid', $modelo->payment_status);
        $this->assertSame('ana.perez@example.com', $modelo->guest_email);
        $this->assertSame('Ana Pérez', $modelo->address->full_name);
        $this->assertSame('Av. Siempre Viva 742', $modelo->address->address_line);
        $this->assertSame('Santa Cruz', $modelo->address->city);
        $this->assertSame('76543210', $modelo->address->phone);
        $this->assertSame('Bolivia', $modelo->address->country);
        $this->assertSame('paypal', $modelo->email_verificado_por);
        // El escrito no se pisa al volver de PayPal.
        $this->assertSame('escrito@example.com', $modelo->notification_email);

        // El correo de pago confirmado sale una vez, al email escrito.
        Mail::assertSent(OrderConfirmedMail::class, 1);
        Mail::assertSent(OrderConfirmedMail::class, fn ($mail) => $mail->hasTo('escrito@example.com'));
        Mail::assertNotSent(OrderReceivedMail::class);
    }

    public function test_a_country_that_does_not_match_the_charged_one_is_flagged_for_review(): void
    {
        $order = $this->crearPedidoExpress();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]),
            // El cliente eligió Bolivia en el carrito pero su dirección de PayPal es de Brasil.
            '*/v2/checkout/orders/*/capture' => Http::response($this->respuestaDeCaptura($order['total'], 'BR')),
        ]);

        $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-EXPRESS-1', [], [
            'X-Order-Token' => $order['access_token'],
        ])->assertOk();

        $modelo = Order::with('address')->findOrFail($order['id']);
        $this->assertSame('Brasil', $modelo->address->country);
        $this->assertStringContainsString('Revisar diferencia', (string) $modelo->notes);
        // Misma marca que "pagado sin stock": insignia y filtro en el panel.
        $this->assertStringContainsString('PayPal informó Brasil', (string) $modelo->attention_reason);
    }

    /**
     * Ninguna pasarela avisa "recibimos tu pedido" al crearlo, porque el cobro todavía
     * no ocurrió. Con Stripe esto llegó a pasar de verdad: la tarjeta la rechazó el
     * banco y el cliente igual recibió el correo, porque la condición solo excluía a
     * PayPal por nombre.
     */
    public function test_no_gateway_announces_the_order_before_the_payment(): void
    {
        foreach (['stripe', 'mercadopago', 'paypal'] as $metodo) {
            Mail::fake();
            $variant = $this->variante();

            $this->postJson('/api/v1/orders', [
                'guest_email' => 'invitado@example.com',
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'address' => [
                    'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                    'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
                ],
                'payment_method' => $metodo,
            ])->assertCreated();

            Mail::assertNotSent(OrderReceivedMail::class, "método: {$metodo}");
        }
    }

    /** Ninguno de los métodos de hoy se coordina a mano: todos son pasarelas. */
    public function test_no_current_method_requires_manual_coordination(): void
    {
        foreach (['paypal', 'stripe', 'mercadopago'] as $metodo) {
            $order = new Order;
            $order->payment_method = $metodo;

            $this->assertFalse($order->requiereCoordinacionManual(), "método: {$metodo}");
        }

        // La lista está vacía a propósito. El día que se agregue un método coordinado a
        // mano (transferencia, contra entrega), sumarlo a esta constante es lo único que
        // hace falta para que vuelva a salir el correo de "recibimos tu pedido".
        $this->assertSame([], Order::METODOS_CON_COORDINACION_MANUAL);
    }

    public function test_express_requires_a_valid_shipping_option(): void
    {
        $variant = $this->variante();

        $this->postJson('/api/v1/orders/paypal-express', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'shipping_country_code' => 'BO',
            'email' => 'escrito@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('shipping_option_id');

        // Una opción de otro país no sirve para este destino.
        $otroPais = $this->opcionDeEnvio(['country_code' => 'BR']);
        $this->postJson('/api/v1/orders/paypal-express', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'shipping_country_code' => 'BO',
            'shipping_option_id' => $otroPais->id,
            'email' => 'escrito@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('shipping_option_id');
    }
}
