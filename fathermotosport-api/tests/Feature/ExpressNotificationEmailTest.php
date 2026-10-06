<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Mail\PaymentRecoveryMail;
use App\Mail\ShippingUpdateMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use App\Models\User;
use App\Services\EmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PayPal Express con dos emails: el que el cliente escribe en la tienda (para avisarle,
 * notification_email) y el que informa PayPal al pagar (verificado, guest_email). Los
 * avisos van al escrito; vincular pedidos y crear la cuenta, solo al de PayPal.
 *
 * También la captura desde el webhook CHECKOUT.ORDER.APPROVED, para el cliente que
 * aprobó en PayPal y no volvió al sitio.
 */
class ExpressNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private const ESCRITO = 'escrito@example.com';

    private const PAYPAL = 'ana.perez@example.com';

    private const PAYPAL_ORDER = 'PAYPAL-EXPRESS-1';

    private const PASSWORD = 'Clave#Segura1';

    /** Respuesta de PayPal al capturar: [status HTTP, cuerpo]. */
    private array $captura = [200, []];

    /** Respuesta de PayPal al consultar la orden (GET). */
    private array $consulta = ['status' => 'CREATED'];

    private ProductVariant $variante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        config(['services.paypal.webhook_id' => 'WH-TEST', 'services.paypal.currency' => 'USD']);

        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/v1/oauth2/token') => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
                str_contains($url, '/verify-webhook-signature') => Http::response(['verification_status' => 'SUCCESS']),
                str_contains($url, '/capture') => Http::response($this->captura[1], $this->captura[0]),
                str_contains($url, '/v2/checkout/orders/') => Http::response($this->consulta),
                default => Http::response([], 404),
            };
        });

        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $this->variante = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);
    }

    private function express(array $datos = []): TestResponse
    {
        $opcion = ShippingOption::firstOrCreate(['country_code' => 'BO', 'method_name' => 'Express DHL'], [
            'min_weight_kg' => 0, 'max_weight_kg' => null, 'price' => 25, 'currency' => 'USD',
            'estimated_days_min' => 5, 'estimated_days_max' => 7, 'is_active' => true,
        ]);

        return $this->postJson('/api/v1/orders/paypal-express', array_merge([
            'items' => [['variant_id' => $this->variante->id, 'quantity' => 2]],
            'shipping_country_code' => 'BO',
            'shipping_option_id' => $opcion->id,
            'email' => self::ESCRITO,
        ], $datos));
    }

    /** Pedido Express creado y con su orden de PayPal, como tras paypalCreate. */
    private function pedidoExpress(string $email = self::ESCRITO): Order
    {
        $order = Order::findOrFail($this->express(['email' => $email])->assertCreated()->json('order.id'));

        Payment::create([
            'order_id' => $order->id, 'provider' => 'paypal', 'transaction_id' => self::PAYPAL_ORDER,
            'currency' => 'USD', 'amount' => $order->total, 'status' => 'pending',
        ]);

        return $order;
    }

    /** Orden de PayPal capturada, como la devuelven la captura y la consulta. */
    private function capturada(Order $order, array $cambios = []): array
    {
        return array_replace_recursive([
            'id' => self::PAYPAL_ORDER,
            'status' => 'COMPLETED',
            'payer' => [
                'name' => ['given_name' => 'Ana', 'surname' => 'Pérez'],
                'email_address' => self::PAYPAL,
                'phone' => ['phone_number' => ['national_number' => '76543210']],
            ],
            'purchase_units' => [[
                'shipping' => [
                    'name' => ['full_name' => 'Ana Pérez'],
                    'address' => [
                        'address_line_1' => 'Av. Siempre Viva 742', 'admin_area_2' => 'Santa Cruz',
                        'admin_area_1' => 'Santa Cruz', 'postal_code' => '0000', 'country_code' => 'BO',
                    ],
                ],
                'payments' => ['captures' => [[
                    'status' => 'COMPLETED',
                    'amount' => ['value' => number_format((float) $order->total, 2, '.', ''), 'currency_code' => 'USD'],
                ]]],
            ]],
        ], $cambios);
    }

    private function volverDePaypal(Order $order): TestResponse
    {
        return $this->postJson('/api/v1/payments/paypal/capture/'.self::PAYPAL_ORDER, [], ['X-Order-Token' => $order->access_token]);
    }

    private function webhookAprobado(): TestResponse
    {
        return $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => self::PAYPAL_ORDER, 'status' => 'APPROVED'],
        ]);
    }

    // ───────── El campo de email ─────────

    public function test_express_without_email_is_rejected(): void
    {
        $this->express(['email' => null])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_express_with_an_invalid_email_is_rejected(): void
    {
        foreach (['no-es-un-email', 'ana@', '@example.com'] as $malo) {
            $this->express(['email' => $malo])->assertStatus(422)->assertJsonValidationErrors('email');
        }

        $this->assertDatabaseCount('orders', 0);
    }

    // ───────── Dos emails, ninguno se pierde ─────────

    public function test_the_written_email_is_kept_and_paypal_s_is_stored_verified_apart(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];

        $this->volverDePaypal($order)->assertOk()->assertJsonPath('status', 'COMPLETED');

        $order->refresh();
        $this->assertSame(self::ESCRITO, $order->notification_email);
        $this->assertSame(self::PAYPAL, $order->guest_email);
        $this->assertSame('paypal', $order->email_verificado_por);
        // Quedó constancia de la diferencia para quien mire el pedido.
        $this->assertStringContainsString('Email de PayPal ('.self::PAYPAL.') distinto del escrito en la tienda ('.self::ESCRITO.')', (string) $order->notes);
    }

    public function test_when_both_emails_match_there_is_no_note_and_mail_goes_to_it(): void
    {
        $order = $this->pedidoExpress(self::PAYPAL);
        $this->captura = [200, $this->capturada($order)];

        $this->volverDePaypal($order)->assertOk();

        $order->refresh();
        $this->assertStringNotContainsString('Email de PayPal', (string) $order->notes);
        Mail::assertSent(OrderConfirmedMail::class, fn ($mail) => $mail->hasTo(self::PAYPAL));
    }

    public function test_confirmation_and_shipping_notices_go_to_the_written_email(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];
        $this->volverDePaypal($order)->assertOk();

        Mail::assertSent(OrderConfirmedMail::class, fn ($mail) => $mail->hasTo(self::ESCRITO) && ! $mail->hasTo(self::PAYPAL));

        app(EmailService::class)->sendShippingUpdate($order->fresh(), 'TRK-1', 'DHL');
        Mail::assertSent(ShippingUpdateMail::class, fn ($mail) => $mail->hasTo(self::ESCRITO));
    }

    public function test_orders_without_notification_email_keep_using_the_usual_email(): void
    {
        // Pedido anterior a la columna: guest_email y nada más.
        $invitado = new Order(['guest_email' => 'viejo@example.com']);
        $this->assertSame('viejo@example.com', $invitado->emailDelCliente());

        // Con cuenta y sin guest_email: el de la cuenta.
        $user = User::factory()->create(['email' => 'cuenta@example.com']);
        $conCuenta = new Order;
        $conCuenta->setRelation('user', $user);
        $this->assertSame('cuenta@example.com', $conCuenta->emailDelCliente());

        // Y el checkout normal no la llena: todo sigue como antes.
        $id = $this->postJson('/api/v1/orders', [
            'guest_email' => 'tarjeta@example.com',
            'items' => [['variant_id' => $this->variante->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order.id');
        $this->assertNull(Order::find($id)->notification_email);
        $this->assertSame('tarjeta@example.com', Order::find($id)->emailDelCliente());
    }

    // ───────── Cuentas: solo el email de PayPal ─────────

    public function test_register_from_order_and_linking_use_only_the_paypal_email(): void
    {
        // Pedidos de invitado anteriores, ya pagados.
        $conElDePaypal = $this->pedidoExpress();
        $conElDePaypal->forceFill(['guest_email' => self::PAYPAL, 'payment_status' => 'paid', 'status' => 'processing'])->save();
        Payment::where('order_id', $conElDePaypal->id)->update(['transaction_id' => 'OTRA-1']);

        // Escribió el email de PayPal en la tienda, pero su email verificado es otro:
        // no se le puede atribuir a quien controla la casilla de PayPal.
        $soloEscrito = $this->pedidoExpress(self::PAYPAL);
        $soloEscrito->forceFill(['guest_email' => 'otro@example.com', 'payment_status' => 'paid', 'status' => 'processing'])->save();
        Payment::where('order_id', $soloEscrito->id)->update(['transaction_id' => 'OTRA-2']);

        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];
        $this->volverDePaypal($order)->assertOk();

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $order->access_token])->assertCreated();

        $user = User::where('email', self::PAYPAL)->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(User::where('email', self::ESCRITO)->exists());
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->assertSame($user->id, $conElDePaypal->fresh()->user_id);
        $this->assertNull($soloEscrito->fresh()->user_id);
    }

    public function test_without_a_paypal_email_the_written_one_never_creates_the_account(): void
    {
        $order = $this->pedidoExpress();
        $order->forceFill(['payment_status' => 'paid', 'status' => 'processing'])->save();

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $order->access_token])->assertStatus(409);

        $this->assertFalse(User::where('email', self::ESCRITO)->exists());
    }

    // ───────── Recuperación de pago en Express ─────────

    public function test_an_abandoned_express_order_gets_the_recovery_mail_at_the_written_email(): void
    {
        // Abandonó antes de ir a PayPal: no hay orden de PayPal que consultar.
        $order = Order::findOrFail($this->express()->assertCreated()->json('order.id'));

        $this->travel(6)->minutes();
        $this->artisan('payments:recover-pending')->assertSuccessful();

        Mail::assertSent(PaymentRecoveryMail::class, fn ($mail) => $mail->hasTo(self::ESCRITO) && $mail->order->is($order));
    }

    public function test_an_express_order_that_went_to_paypal_and_did_not_approve_gets_it(): void
    {
        $order = $this->pedidoExpress();
        $this->consulta = ['id' => self::PAYPAL_ORDER, 'status' => 'PAYER_ACTION_REQUIRED'];

        $this->travel(6)->minutes();
        $this->artisan('payments:recover-pending')->assertSuccessful();

        Mail::assertSent(PaymentRecoveryMail::class, fn ($mail) => $mail->hasTo(self::ESCRITO));
    }

    public function test_no_recovery_mail_after_approval_or_capture(): void
    {
        // Aprobó en PayPal (todavía sin capturar): no se le dice que no pagó.
        $aprobado = $this->pedidoExpress();
        $this->consulta = ['id' => self::PAYPAL_ORDER, 'status' => 'APPROVED'];

        $this->travel(6)->minutes();
        $this->artisan('payments:recover-pending')->assertSuccessful();
        Mail::assertNotSent(PaymentRecoveryMail::class);
        $this->assertNull($aprobado->fresh()->recovery_email_sent_at);

        // Capturado y pagado: tampoco.
        $this->captura = [200, $this->capturada($aprobado)];
        $this->volverDePaypal($aprobado)->assertOk();
        $this->artisan('payments:recover-pending')->assertSuccessful();
        Mail::assertNotSent(PaymentRecoveryMail::class);
    }

    // ───────── Webhook CHECKOUT.ORDER.APPROVED ─────────

    public function test_an_approval_webhook_captures_the_order_when_the_customer_never_returns(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];

        $this->webhookAprobado()->assertOk()->assertJsonPath('handled', true);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('Ana Pérez', $order->address->full_name);
        $this->assertSame(self::PAYPAL, $order->guest_email);
        $this->assertSame(self::ESCRITO, $order->notification_email);
        $this->assertSame(3, $this->variante->fresh()->stock);
        Mail::assertSent(OrderConfirmedMail::class, 1);
        Mail::assertSent(OrderConfirmedMail::class, fn ($mail) => $mail->hasTo(self::ESCRITO));
    }

    public function test_webhook_first_then_return_charges_and_discounts_once(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];
        $this->webhookAprobado()->assertOk();

        // El cliente vuelve después: la orden ya está capturada, y el pedido pagado.
        $this->captura = [422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]]];
        $this->volverDePaypal($order)->assertOk()->assertJsonPath('status', 'COMPLETED');

        $this->assertSame(3, $this->variante->fresh()->stock);
        Mail::assertSent(OrderConfirmedMail::class, 1);
        $this->assertNull($order->fresh()->payment_failed_at);
    }

    public function test_return_finds_the_order_already_captured_but_not_yet_processed(): void
    {
        // El webhook capturó en PayPal pero todavía no terminó de procesar el pedido:
        // el retorno recibe ORDER_ALREADY_CAPTURED, relee la orden y la procesa él.
        $order = $this->pedidoExpress();
        $this->captura = [422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]]];
        $this->consulta = $this->capturada($order);

        $this->volverDePaypal($order)->assertOk()->assertJsonPath('status', 'COMPLETED');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->payment_failed_at);

        // Y si el webhook llega después, no hace nada más.
        $this->webhookAprobado()->assertOk()->assertJsonPath('handled', true);
        $this->assertSame(3, $this->variante->fresh()->stock);
        Mail::assertSent(OrderConfirmedMail::class, 1);
    }

    public function test_return_first_then_webhook_does_nothing_more(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [200, $this->capturada($order)];
        $this->volverDePaypal($order)->assertOk();

        $this->webhookAprobado()->assertOk()->assertJsonPath('handled', true);

        // Una sola captura en PayPal: el webhook vio el pedido pagado y no capturó.
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), '/capture')));
        $this->assertSame(3, $this->variante->fresh()->stock);
        Mail::assertSent(OrderConfirmedMail::class, 1);
    }

    public function test_the_webhook_capture_rejects_a_wrong_amount_or_currency(): void
    {
        foreach ([
            'monto' => ['purchase_units' => [['payments' => ['captures' => [['amount' => ['value' => '1.00']]]]]]],
            'moneda' => ['purchase_units' => [['payments' => ['captures' => [['amount' => ['currency_code' => 'BRL']]]]]]],
        ] as $caso => $cambio) {
            // Cada caso con su propia orden de PayPal del mismo id (transaction_id es único).
            Payment::query()->delete();
            $order = $this->pedidoExpress();
            $this->captura = [200, $this->capturada($order, $cambio)];

            $this->webhookAprobado()->assertOk()->assertJsonPath('handled', false);

            $order->refresh();
            $this->assertSame('pending', $order->payment_status, $caso);
            $this->assertNotNull($order->attention_reason, $caso);
            $this->assertSame(5, $this->variante->fresh()->stock, $caso);
            Mail::assertNotSent(OrderConfirmedMail::class);
        }
    }

    public function test_a_declined_capture_from_the_webhook_only_records_the_failure(): void
    {
        $order = $this->pedidoExpress();
        $this->captura = [422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]]];

        $this->webhookAprobado()->assertOk()->assertJsonPath('handled', false);

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull($order->payment_failed_at);
        $this->assertSame(5, $this->variante->fresh()->stock);
    }
}
