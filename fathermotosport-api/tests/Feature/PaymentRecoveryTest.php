<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Mail\PaymentRecoveryMail;
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
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Correo de recuperación de pago (payments:recover-pending): un único aviso al cliente
 * que no completó el pago, nunca a quien ya pagó, y nunca dos veces.
 */
class PaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'invitado@example.com';

    /** Estado del PaymentIntent que devuelve Stripe al consultarlo. */
    private string $estadoStripe = 'requires_payment_method';

    /** Estado de la orden de PayPal al consultarla. */
    private string $estadoPaypal = 'CREATED';

    /** Si es true, las consultas a la pasarela responden 500. */
    private bool $pasarelaCaida = false;

    /** Se ejecuta en medio de la consulta a la pasarela (para simular carreras). */
    private ?\Closure $alConsultar = null;

    /** Respuesta de PayPal al capturar: [status HTTP, cuerpo]. */
    private array $captura = [200, ['status' => 'COMPLETED']];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        // Un solo fake para Stripe y PayPal; las respuestas se cambian con las
        // propiedades de arriba (registrar Http::fake de nuevo no reemplaza el anterior).
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token-paypal', 'expires_in' => 3600]);
            }

            if (str_contains($url, '/capture')) {
                return Http::response($this->captura[1], $this->captura[0]);
            }

            if ($this->alConsultar) {
                ($this->alConsultar)();
            }

            if ($this->pasarelaCaida) {
                return Http::response(['error' => 'caída'], 500);
            }

            if (str_contains($url, '/v1/payment_intents/')) {
                return Http::response(['id' => 'pi_1', 'status' => $this->estadoStripe, 'amount' => 10000]);
            }

            if (str_contains($url, '/v2/checkout/orders/')) {
                return Http::response(['id' => 'PAYPAL-1', 'status' => $this->estadoPaypal]);
            }

            return Http::response([], 404);
        });
    }

    /** Pedido de invitado por el checkout normal, recién creado. */
    private function pedido(string $locale = 'es', string $metodo = 'stripe'): Order
    {
        $product = Product::factory()->create(['name' => 'Casco Integral Prueba', 'price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $id = $this->postJson('/api/v1/orders', [
            'guest_email' => self::EMAIL,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Carla Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => $metodo,
            'locale' => $locale,
        ])->assertCreated()->json('order.id');

        return Order::findOrFail($id);
    }

    private function pago(Order $order, string $provider = 'stripe', string $id = 'pi_1'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'transaction_id' => $id,
            'currency' => 'USD',
            'amount' => $order->total,
            'status' => 'pending',
        ]);
    }

    private function recuperar(): void
    {
        $this->artisan('payments:recover-pending')->assertSuccessful();
    }

    private function assertAvisoEnviado(Order $order, int $veces = 1): void
    {
        Mail::assertSent(PaymentRecoveryMail::class, $veces);
        Mail::assertSent(PaymentRecoveryMail::class, fn ($mail) => $mail->hasTo(self::EMAIL) && $mail->order->is($order));
        $this->assertNotNull($order->fresh()->recovery_email_sent_at);
    }

    private function assertSinAviso(Order $order): void
    {
        Mail::assertNotSent(PaymentRecoveryMail::class);
        $this->assertNull($order->fresh()->recovery_email_sent_at);
    }

    // ───────── Cuándo sale ─────────

    public function test_not_sent_at_4_minutes_and_sent_at_5(): void
    {
        $order = $this->pedido();

        $this->travel(4)->minutes();
        $this->recuperar();
        $this->assertSinAviso($order);

        $this->travel(1)->minutes();
        $this->recuperar();
        $this->assertAvisoEnviado($order);
        // Solo avisa: el pedido sigue pendiente, no se cancela ni se toca nada más.
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_silent_abandonment_is_not_notified_after_30_minutes(): void
    {
        // A esa altura orders:cancel-abandoned ya lo da por muerto.
        $order = $this->pedido();

        $this->travel(31)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_an_explicit_failure_is_notified_5_minutes_after_the_failure(): void
    {
        $order = $this->pedido();

        // Rechazo a los 2 minutos de crear el pedido.
        $this->travel(2)->minutes();
        $order->forceFill(['payment_failed_at' => now()])->save();

        // A los 5 de creado van 3 del rechazo: todavía no.
        $this->travel(3)->minutes();
        $this->recuperar();
        $this->assertSinAviso($order);

        $this->travel(2)->minutes();
        $this->recuperar();
        $this->assertAvisoEnviado($order);
    }

    public function test_an_explicit_failure_is_notified_even_after_the_abandonment_window(): void
    {
        $order = $this->pedido();
        $this->travel(40)->minutes();
        $order->forceFill(['payment_failed_at' => now()])->save();

        $this->travel(5)->minutes();
        $this->recuperar();

        $this->assertAvisoEnviado($order);
    }

    public function test_an_intent_still_waiting_for_a_payment_method_is_notified(): void
    {
        $order = $this->pedido();
        $this->pago($order);
        $this->estadoStripe = 'requires_payment_method';

        $this->travel(5)->minutes();
        $this->recuperar();

        $this->assertAvisoEnviado($order);
    }

    // ───────── Cuándo nunca sale ─────────

    public function test_a_paid_order_is_not_notified(): void
    {
        $order = $this->pedido();
        $order->update(['payment_status' => 'paid', 'status' => 'processing']);

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_a_cancelled_order_is_not_notified(): void
    {
        $order = $this->pedido();
        $order->update(['status' => 'cancelled']);

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_an_order_flagged_for_attention_is_not_notified(): void
    {
        $order = $this->pedido();
        $order->update(['attention_reason' => 'Monto distinto.']);

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_an_order_already_notified_is_not_notified_again(): void
    {
        $order = $this->pedido();
        $order->forceFill(['recovery_email_sent_at' => now()])->save();

        $this->travel(6)->minutes();
        $this->recuperar();

        Mail::assertNotSent(PaymentRecoveryMail::class);
    }

    public function test_an_upsell_order_is_not_notified(): void
    {
        $original = $this->pedido();
        $original->update(['payment_status' => 'paid', 'status' => 'processing']);
        $upsell = $this->pedido();
        $upsell->forceFill(['upsell_of_order_id' => $original->id])->save();

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($upsell);
    }

    public function test_an_order_without_email_is_not_notified(): void
    {
        // Como un PayPal Express sin terminar: el email llega recién con la captura.
        $order = $this->pedido();
        $order->forceFill(['guest_email' => null, 'user_id' => null])->save();

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_a_registered_customer_gets_it_at_the_account_email(): void
    {
        $order = $this->pedido();
        $user = User::factory()->create(['email' => 'cuenta@example.com', 'first_name' => 'Rocío']);
        $order->forceFill(['guest_email' => null, 'user_id' => $user->id])->save();

        $this->travel(6)->minutes();
        $this->recuperar();

        Mail::assertSent(PaymentRecoveryMail::class, fn ($mail) => $mail->hasTo('cuenta@example.com'));
    }

    // ───────── Una sola vez ─────────

    public function test_running_twice_sends_only_once(): void
    {
        $order = $this->pedido();
        $this->travel(6)->minutes();

        $this->recuperar();
        $this->recuperar();

        $this->assertAvisoEnviado($order, 1);
    }

    public function test_an_order_paid_between_selection_and_sending_is_not_notified(): void
    {
        $order = $this->pedido();
        $this->pago($order);
        // El pago entra (webhook) mientras el comando consulta la pasarela, después de
        // haber elegido el pedido como candidato.
        $this->alConsultar = fn () => Order::whereKey($order->id)->update(['payment_status' => 'paid', 'status' => 'processing']);

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
    }

    public function test_if_sending_fails_it_is_not_marked_and_is_retried(): void
    {
        $order = $this->pedido();
        $this->travel(6)->minutes();
        Log::spy();

        $mailerReal = Mail::getFacadeRoot()->manager;
        $mailerRoto = Mockery::mock();
        $mailerRoto->shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP caído'));
        Mail::swap($mailerRoto);
        $this->recuperar();

        $this->assertNull($order->fresh()->recovery_email_sent_at);
        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje) => str_contains($mensaje, 'falló el envío'));

        // En la próxima ejecución, con el correo funcionando, sale.
        Mail::swap($mailerReal);
        Mail::fake();
        $this->recuperar();
        $this->assertAvisoEnviado($order);
    }

    // ───────── Consulta a la pasarela ─────────

    public function test_a_payment_intent_requiring_action_or_processing_is_not_notified(): void
    {
        foreach (['requires_action', 'processing'] as $estado) {
            $order = $this->pedido();
            $this->pago($order, 'stripe', 'pi_'.$estado);
            $this->estadoStripe = $estado;

            $this->travel(6)->minutes();
            $this->recuperar();

            $this->assertSinAviso($order);
            $order->update(['status' => 'cancelled']);
        }
    }

    public function test_an_approved_paypal_order_is_not_notified(): void
    {
        $order = $this->pedido('es', 'paypal');
        $this->pago($order, 'paypal', 'PAYPAL-1');
        $this->estadoPaypal = 'APPROVED';

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/v2/checkout/orders/PAYPAL-1'));
    }

    public function test_an_unapproved_paypal_order_is_notified(): void
    {
        $order = $this->pedido('es', 'paypal');
        $this->pago($order, 'paypal', 'PAYPAL-1');
        $this->estadoPaypal = 'PAYER_ACTION_REQUIRED';

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertAvisoEnviado($order);
    }

    public function test_if_the_gateway_query_fails_nothing_is_sent_and_it_is_logged(): void
    {
        $order = $this->pedido();
        $this->pago($order);
        $this->pasarelaCaida = true;
        Log::spy();

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertSinAviso($order);
        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje) => str_contains($mensaje, 'no se pudo consultar la pasarela'));
    }

    public function test_an_order_without_a_gateway_payment_is_not_queried(): void
    {
        $order = $this->pedido();

        $this->travel(6)->minutes();
        $this->recuperar();

        $this->assertAvisoEnviado($order);
        Http::assertNothingSent();
    }

    // ───────── El correo ─────────

    public function test_the_mail_offers_paypal_and_card_in_the_three_languages(): void
    {
        $esperado = [
            'es' => ['No pudimos recibir tu pago en FatherMotoSport', 'tarjeta de crédito o débito', 'Completar mi pago', 'Este es un único aviso sobre este pedido.', ''],
            'pt' => ['Não conseguimos receber seu pagamento na FatherMotoSport', 'cartão de crédito ou débito', 'Concluir meu pagamento', 'Este é um aviso único sobre este pedido.', '/pt'],
            'en' => ["We couldn't receive your payment at FatherMotoSport", 'credit or debit card', 'Complete my payment', "This is the only notice we'll send about this order.", '/en'],
        ];

        foreach ($esperado as $locale => [$asunto, $tarjeta, $boton, $unico, $prefijo]) {
            $order = $this->pedido($locale);
            $mail = new PaymentRecoveryMail($order);
            $html = html_entity_decode($mail->render(), ENT_QUOTES);

            $mail->assertHasSubject($asunto);
            $this->assertStringContainsString('PayPal', $html);
            $this->assertStringContainsString($tarjeta, $html);
            $this->assertStringContainsString($boton, $html);
            $this->assertStringContainsString($unico, $html);
            $this->assertStringContainsString('contacto@fathermotosport.com', $html);
            // Resumen: número, producto y total; saludo con el nombre de la dirección.
            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringContainsString('Casco Integral Prueba', $html);
            $this->assertStringContainsString('$'.number_format($order->total, 2), $html);
            $this->assertStringContainsString('Carla', $html);
            // El botón retoma el pedido con su token, en el idioma del pedido.
            $base = rtrim(config('app.frontend_url'), '/');
            $this->assertStringContainsString("{$base}{$prefijo}/checkout/pay/{$order->id}?t={$order->access_token}", $html);
        }
    }

    public function test_the_mail_shows_the_payment_badges_and_the_button_in_the_three_languages(): void
    {
        config(['app.email_assets_url' => 'https://fathermotosport.com']);
        $botones = ['es' => 'Completar mi pago', 'pt' => 'Concluir meu pagamento', 'en' => 'Complete my payment'];
        $metodos = [
            'es' => 'Paga con PayPal o con tarjeta de crédito o débito',
            'pt' => 'Pague com PayPal ou com cartão de crédito ou débito',
            'en' => 'Pay with PayPal or with a credit or debit card',
        ];

        foreach ($botones as $locale => $boton) {
            $order = $this->pedido($locale);
            $html = html_entity_decode((new PaymentRecoveryMail($order))->render(), ENT_QUOTES);

            // Insignias PNG desde una URL pública de la tienda, con texto alternativo y medidas.
            foreach (['paypal' => 'PayPal', 'visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'American Express'] as $archivo => $alt) {
                $this->assertMatchesRegularExpression(
                    '#<img src="https://fathermotosport\.com/email/payment-badges/'.$archivo.'\.png" width="56" height="36" alt="'.$alt.'"#',
                    $html,
                    "{$locale}: insignia {$alt}"
                );
            }
            $this->assertStringNotContainsString('.svg', $html);
            $this->assertStringNotContainsString('data:image', $html);
            $this->assertStringContainsString($metodos[$locale], $html);

            // El botón, con su enlace, también en la versión VML para Outlook.
            $this->assertStringContainsString($boton, $html);
            $this->assertSame(2, substr_count($html, 'href="'.$order->urlParaPagar().'"'), $locale);
        }
    }

    public function test_the_mail_never_uses_text_smaller_than_13px(): void
    {
        $html = (new PaymentRecoveryMail($this->pedido()))->render();
        // Fuera del texto de vista previa, que está oculto a propósito.
        $visible = preg_replace('#<div style="display:none.*?</div>#s', '', $html);

        preg_match_all('/font-size:\s*(\d+)px/', $visible, $tamanos);

        $this->assertNotEmpty($tamanos[1]);
        $this->assertGreaterThanOrEqual(13, min(array_map('intval', $tamanos[1])));
    }

    public function test_the_product_thumbnail_appears_only_when_there_is_an_image(): void
    {
        $order = $this->pedido();
        // La factory le agrega imágenes al producto: se parte de uno sin ninguna.
        $producto = $order->items()->first()->variant->product;
        $producto->images()->delete();

        $html = (new PaymentRecoveryMail($order->fresh()))->render();
        $this->assertStringNotContainsString('alt="Casco Integral Prueba"', $html);
        $this->assertStringNotContainsString('width="64" height="64"', $html);

        $producto->images()->create(['url' => 'https://cdn.example.com/casco.jpg', 'is_primary' => true, 'sort_order' => 0]);

        $html = (new PaymentRecoveryMail($order->fresh()))->render();
        $this->assertStringContainsString('<img src="https://cdn.example.com/casco.jpg" width="64" height="64" alt="Casco Integral Prueba"', $html);
    }

    public function test_the_mail_has_no_countdown_urgency_or_discount(): void
    {
        // Solo el texto que lee el cliente: el CSS del layout trae clases como .countdown
        // que usan otros correos.
        $html = (new PaymentRecoveryMail($this->pedido()))->render();
        $texto = mb_strtolower(html_entity_decode(strip_tags(preg_replace('/<style.*?<\/style>/s', '', $html)), ENT_QUOTES));

        foreach (['countdown', 'cupón', 'descuento exclusivo', 'últimas horas', 'expira', 'apúrate', '% off'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $texto);
        }
    }

    public function test_an_order_without_locale_gets_the_mail_in_spanish(): void
    {
        $order = $this->pedido();
        $order->forceFill(['locale' => null])->save();

        (new PaymentRecoveryMail($order))->assertHasSubject('No pudimos recibir tu pago en FatherMotoSport');
    }

    public function test_the_other_mails_keep_their_spanish_footer(): void
    {
        // El pie del layout se tradujo solo para este correo: los demás siguen igual
        // aunque APP_LOCALE sea otro.
        app()->setLocale('en');
        $order = $this->pedido();
        $order->update(['payment_status' => 'paid']);

        $html = html_entity_decode((new OrderConfirmedMail($order))->render(), ENT_QUOTES);

        $this->assertStringContainsString('Envío gratuito a Bolivia y Brasil', $html);
        $this->assertStringContainsString('Ver tienda', $html);
    }

    // ───────── Idioma del pedido ─────────

    public function test_the_checkout_stores_the_order_locale_and_ignores_an_unknown_one(): void
    {
        $this->assertSame('pt', $this->pedido('pt')->locale);
        $this->assertNull($this->pedido('fr')->locale);
    }

    // ───────── Rechazos de la pasarela ─────────

    /** Firma el payload como lo hace Stripe: HMAC-SHA256 de "timestamp.cuerpo". */
    private function webhookStripe(array $evento): TestResponse
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $payload = json_encode($evento);
        $t = time();

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test'),
        ], $payload);
    }

    public function test_the_stripe_payment_failed_webhook_only_records_the_failure(): void
    {
        $order = $this->pedido();
        $this->pago($order);

        $this->webhookStripe([
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => 'pi_1',
                'status' => 'requires_payment_method',
                'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds'],
            ]],
        ])->assertOk();

        $order->refresh();
        $this->assertNotNull($order->payment_failed_at);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', Payment::where('transaction_id', 'pi_1')->value('status'));
        Mail::assertNotSent(PaymentRecoveryMail::class);
    }

    public function test_a_late_payment_failed_webhook_does_not_touch_a_paid_order(): void
    {
        $order = $this->pedido();
        $this->pago($order);
        $order->update(['payment_status' => 'paid', 'status' => 'processing']);

        $this->webhookStripe([
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_1']],
        ])->assertOk();

        $this->assertNull($order->fresh()->payment_failed_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_declined_paypal_capture_records_the_failure_but_returning_without_approving_does_not(): void
    {
        $order = $this->pedido('es', 'paypal');
        $this->pago($order, 'paypal', 'PAYPAL-1');
        $capturar = fn () => $this->postJson('/api/v1/payments/paypal/capture/PAYPAL-1', [], ['X-Order-Token' => $order->access_token]);

        // Volvió sin aprobar: es un abandono, no un rechazo.
        $this->captura = [422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_NOT_APPROVED']]]];
        $capturar()->assertStatus(422);
        $this->assertNull($order->fresh()->payment_failed_at);

        // La captura se rechazó: sí es un pago fallido.
        $this->captura = [422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]]];
        $capturar()->assertStatus(422);
        $this->assertNotNull($order->fresh()->payment_failed_at);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
