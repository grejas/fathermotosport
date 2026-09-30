<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stripe en modo Test: las mismas guardas que PayPal.
 *
 * Antes stripeIntent no validaba nada: con solo conocer el UUID de un pedido se podían
 * generar cobros sobre la compra de otra persona, y un pedido ya pagado se podía volver
 * a cobrar. stripeConfirm además devolvía el modelo Order completo, con access_token.
 */
class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    /** Id que devolverá el próximo POST (creación de intent). */
    private string $intentNuevo = 'pi_test_1';

    /** Campos que sobreescriben el intent que devuelve el GET (relectura). */
    private array $alRelear = [];

    /**
     * Monto con el que se creó el intent. Se guarda del POST para devolverlo en el GET,
     * como hace Stripe: así el fake no depende de que el total del pedido sea un número
     * concreto, y el test del monto discordante lo fuerza con $alRelear.
     */
    private int $montoCreado = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        // El fake se registra una sola vez: llamar a Http::fake() de nuevo no reemplaza
        // el stub anterior, lo encola, y seguiría respondiendo el primero. Para cambiar
        // la respuesta a mitad del test se mutan las propiedades de arriba.
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                $this->montoCreado = (int) $request['amount'];

                return Http::response([
                    'id' => $this->intentNuevo,
                    'client_secret' => $this->intentNuevo.'_secret_abc',
                    'status' => 'requires_payment_method',
                    'amount' => $this->montoCreado,
                ]);
            }

            return Http::response(array_merge([
                'id' => $this->intentNuevo,
                'client_secret' => $this->intentNuevo.'_secret_abc',
                'status' => 'succeeded',
                'amount' => $this->montoCreado,
            ], $this->alRelear));
        });
    }

    private function variante(int $stock = 5): ProductVariant
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => $stock]);
    }

    /** Crea un pedido de invitado por el checkout normal, con Stripe como método. */
    private function pedido(ProductVariant $variant): Order
    {
        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order');

        return Order::findOrFail($order['id']);
    }

    /**
     * Ajusta lo que responderá Stripe: el id del próximo intent creado (POST) y, si hace
     * falta, los campos del intent al releerlo (GET).
     *
     * @param  array<string, mixed>  $alRelear
     */
    private function fingirStripe(string $intentId = 'pi_test_1', array $alRelear = []): void
    {
        $this->intentNuevo = $intentId;
        $this->alRelear = $alRelear;
    }

    // ───────── Guarda 1: control de acceso ─────────

    public function test_intent_is_refused_without_the_order_token(): void
    {
        $order = $this->pedido($this->variante());
        $this->fingirStripe();

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id])
            ->assertStatus(403);

        // No llegó a crearse ningún cobro ni a tocarse la API de Stripe.
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_intent_is_refused_to_a_logged_in_user_who_does_not_own_the_order(): void
    {
        $order = $this->pedido($this->variante());
        Sanctum::actingAs(User::factory()->create());
        $this->fingirStripe();

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id])
            ->assertStatus(403);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_intent_is_created_with_a_valid_order_token(): void
    {
        $order = $this->pedido($this->variante());
        $this->fingirStripe();

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], [
            'X-Order-Token' => $order->access_token,
        ])
            ->assertOk()
            ->assertJsonPath('payment_intent_id', 'pi_test_1')
            ->assertJsonPath('client_secret', 'pi_test_1_secret_abc');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'stripe',
            'transaction_id' => 'pi_test_1',
            'status' => 'pending',
        ]);

        // Stripe cobra en centavos: el pedido de $200 se envía como 20000.
        $this->assertSame(20000, $this->montoCreado);
        $this->assertSame('200.00', (string) $order->total);
    }

    // ───────── Guarda 2: pedido ya pagado ─────────

    public function test_a_paid_order_cannot_be_charged_again(): void
    {
        $order = $this->pedido($this->variante());
        $order->update(['payment_status' => 'paid', 'status' => 'processing']);
        $this->fingirStripe();

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], [
            'X-Order-Token' => $order->access_token,
        ])->assertStatus(409);

        Http::assertNothingSent();
    }

    // ───────── Guarda 3: pedido cancelado o reembolsado ─────────

    public function test_a_cancelled_or_refunded_order_cannot_be_paid(): void
    {
        foreach (['cancelled', 'refunded'] as $estado) {
            $order = $this->pedido($this->variante());
            $order->update(['status' => $estado]);
            $this->fingirStripe();

            $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], [
                'X-Order-Token' => $order->access_token,
            ])->assertStatus(409, 'estado: '.$estado);
        }
    }

    // ───────── Guarda 4: reutilizar el intent pendiente ─────────

    public function test_a_second_call_reuses_the_pending_intent_instead_of_charging_twice(): void
    {
        $order = $this->pedido($this->variante());
        $this->fingirStripe(alRelear: ['status' => 'requires_payment_method']);
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)
            ->assertOk()
            ->assertJsonMissingPath('reused');

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('payment_intent_id', 'pi_test_1');

        // Un solo cobro registrado: el cliente que recarga no genera un segundo intent.
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_an_intent_stripe_no_longer_accepts_is_not_reused(): void
    {
        $order = $this->pedido($this->variante());
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->fingirStripe();
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)->assertOk();

        // Stripe informa que ese intent quedó cancelado: hay que crear uno nuevo.
        $this->fingirStripe('pi_test_2', ['id' => 'pi_test_1', 'status' => 'canceled']);

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)
            ->assertOk()
            ->assertJsonMissingPath('reused')
            ->assertJsonPath('payment_intent_id', 'pi_test_2');

        $this->assertSame(2, Payment::where('order_id', $order->id)->count());
    }

    public function test_an_intent_whose_amount_no_longer_matches_is_not_reused(): void
    {
        $order = $this->pedido($this->variante());
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->fingirStripe();
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)->assertOk();

        // El intent guardado quedó con otro importe: reutilizarlo cobraría el viejo.
        $this->fingirStripe('pi_test_2', [
            'id' => 'pi_test_1',
            'status' => 'requires_payment_method',
            'amount' => 999,
        ]);

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)
            ->assertOk()
            ->assertJsonPath('payment_intent_id', 'pi_test_2');
    }

    // ───────── stripeConfirm: no filtra datos sensibles ─────────

    public function test_confirm_does_not_leak_the_order_token_or_private_data(): void
    {
        $order = $this->pedido($this->variante());
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->fingirStripe();
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)->assertOk();

        $order->update(['notes' => 'Nota interna que el cliente no debe leer']);

        $respuesta = $this->postJson('/api/v1/payments/stripe/confirm', [
            'payment_intent_id' => 'pi_test_1',
        ], $cabeceras)->assertOk();

        // Lo que el frontend necesita sí está.
        $respuesta->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonPath('order.payment_status', 'paid');

        // Y lo sensible no: el access_token es la llave del pedido para un invitado.
        $cuerpo = $respuesta->getContent();
        $this->assertStringNotContainsString($order->access_token, $cuerpo);
        $this->assertStringNotContainsString('Nota interna', $cuerpo);
        $this->assertStringNotContainsString('Av. Prueba 123', $cuerpo, 'la dirección no viaja');
        $this->assertStringNotContainsString('76543210', $cuerpo, 'el teléfono no viaja');
        $respuesta->assertJsonMissingPath('order.access_token')
            ->assertJsonMissingPath('order.notes')
            ->assertJsonMissingPath('order.address');
    }

    public function test_confirm_is_refused_without_the_order_token(): void
    {
        $order = $this->pedido($this->variante());

        $this->fingirStripe();
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], [
            'X-Order-Token' => $order->access_token,
        ])->assertOk();

        // Conocer el id del intent no alcanza para consultar el pago.
        $this->postJson('/api/v1/payments/stripe/confirm', ['payment_intent_id' => 'pi_test_1'])
            ->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_confirm_of_an_unknown_intent_returns_404(): void
    {
        $this->postJson('/api/v1/payments/stripe/confirm', ['payment_intent_id' => 'pi_inexistente'])
            ->assertStatus(404);
    }

    // ───────── Webhook ─────────

    /** Firma el payload como lo hace Stripe: HMAC-SHA256 de "timestamp.cuerpo". */
    private function enviarWebhook(array $evento, string $secreto = 'whsec_test'): \Illuminate\Testing\TestResponse
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $payload = json_encode($evento);
        $t = time();
        $firma = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secreto);

        return $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $firma],
            $payload
        );
    }

    /** Pago de Stripe pendiente, sin pasar por el endpoint del intent. */
    private function pagoPendiente(Order $order, string $intentId = 'pi_test_1', string $provider = 'stripe'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'transaction_id' => $intentId,
            'currency' => 'USD',
            'amount' => $order->total,
            'status' => 'pending',
        ]);
    }

    /** @param array<string, mixed> $objeto */
    private function eventoIntentExitoso(array $objeto = []): array
    {
        return [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => array_merge([
                'id' => 'pi_test_1',
                'status' => 'succeeded',
                'amount' => 20000,
                'amount_received' => 20000,
            ], $objeto)],
        ];
    }

    public function test_the_webhook_marks_the_order_paid_and_discounts_the_stock(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedido($variant);
        $this->pagoPendiente($order);

        $this->enviarWebhook($this->eventoIntentExitoso())
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertNull($order->fresh()->attention_reason);
    }

    public function test_the_webhook_rejects_an_invalid_signature(): void
    {
        $order = $this->pedido($this->variante());
        $this->pagoPendiente($order);

        // Firmado con otro secreto que el configurado.
        $this->enviarWebhook($this->eventoIntentExitoso(), 'whsec_otro')->assertStatus(400);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_the_webhook_ignores_events_other_than_payment_intent_succeeded(): void
    {
        $order = $this->pedido($this->variante());
        $this->pagoPendiente($order);

        $this->enviarWebhook([
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_test_1', 'amount_received' => 20000]],
        ])->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_the_webhook_only_looks_at_stripe_payments(): void
    {
        $order = $this->pedido($this->variante());
        // Mismo transaction_id pero de otra pasarela: no es el pago de este evento.
        $this->pagoPendiente($order, 'pi_test_1', 'paypal');

        $this->enviarWebhook($this->eventoIntentExitoso())->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_the_webhook_flags_a_mismatched_amount_instead_of_marking_it_paid(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedido($variant);
        $this->pagoPendiente($order);

        Log::spy();

        // El pedido totaliza $200 (20000 centavos) y Stripe informa $150.
        $this->enviarWebhook($this->eventoIntentExitoso(['amount_received' => 15000]))->assertOk();

        $fresco = $order->fresh();
        $this->assertSame('pending', $fresco->payment_status, 'un importe distinto no se acepta en silencio');
        $this->assertStringContainsString('Stripe cobró 150.00', (string) $fresco->attention_reason);
        $this->assertStringContainsString('200.00', (string) $fresco->attention_reason);
        $this->assertStringContainsString('⚠️', (string) $fresco->notes);

        // Sin marcar pagado, tampoco se toca el stock.
        $this->assertSame(5, $variant->fresh()->stock);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $mensaje) => str_contains($mensaje, 'requiere atención manual'))
            ->once();
    }

    public function test_a_successful_confirm_marks_the_order_paid_and_discounts_the_stock(): void
    {
        $variant = $this->variante(5);
        $order = $this->pedido($variant);
        $cabeceras = ['X-Order-Token' => $order->access_token];

        $this->fingirStripe();
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $order->id], $cabeceras)->assertOk();

        $this->assertSame(5, $variant->fresh()->stock, 'el stock se descuenta al pagar, no antes');

        $this->postJson('/api/v1/payments/stripe/confirm', ['payment_intent_id' => 'pi_test_1'], $cabeceras)
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid');

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame('processing', $order->fresh()->status);
    }
}
