<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mercado Pago México: el total en USD se cobra en MXN con el tipo de cambio del día,
 * y el webhook solo marca pagado si lo cobrado cubre ese monto.
 */
class MercadoPagoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec-mp-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
        config([
            'services.mercadopago.access_token' => 'APP_USR-test',
            'services.mercadopago.webhook_secret' => self::SECRET,
        ]);
    }

    private function pedido(): Order
    {
        $product = Product::factory()->create(['price' => 215, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'cliente@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '5512345678', 'country' => 'México',
                'city' => 'CDMX', 'address_line' => 'Av. Reforma 1',
            ],
            'payment_method' => 'mercadopago',
        ])->assertCreated()->json('order');

        return Order::findOrFail($order['id']);
    }

    /** Lo que devuelve GET /v1/payments/{id}; cada test lo cambia entre llamadas. */
    private array $pago = [];

    private bool $fakeado = false;

    private function fakeMp(float $mxnPorUsd = 18.5, array $pago = []): void
    {
        $this->pago = $pago;
        // Http::fake acumula: se registra una sola vez y el pago se lee de $this->pago.
        if ($this->fakeado) {
            return;
        }
        $this->fakeado = true;

        Http::fake([
            'api.frankfurter.app/*' => Http::response(['rates' => ['MXN' => $mxnPorUsd]]),
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'PREF-1', 'init_point' => 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=PREF-1',
            ], 201),
            'api.mercadopago.com/v1/payments/*' => fn () => Http::response($this->pago + [
                'id' => 999, 'status' => 'approved', 'currency_id' => 'MXN', 'payment_type_id' => 'credit_card',
            ]),
        ]);
    }

    private function crear(Order $order)
    {
        return $this->postJson('/api/v1/payments/mercadopago/create', ['order_id' => $order->id], ['X-Order-Token' => $order->access_token]);
    }

    private function webhook(string $paymentId = '999')
    {
        $ts = '1700000000';
        $requestId = 'req-1';
        $firma = hash_hmac('sha256', "id:{$paymentId};request-id:{$requestId};ts:{$ts};", self::SECRET);

        return $this->postJson("/api/v1/webhooks/mercadopago?data.id={$paymentId}&type=payment",
            ['type' => 'payment', 'data' => ['id' => $paymentId]],
            ['x-signature' => "ts={$ts},v1={$firma}", 'x-request-id' => $requestId]);
    }

    public function test_charges_the_order_total_in_mxn_at_the_days_rate(): void
    {
        $this->fakeMp(18.5);
        $order = $this->pedido();

        $res = $this->crear($order)->assertOk()
            ->assertJsonPath('currency', 'MXN')
            ->assertJsonPath('init_point', 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=PREF-1');

        $order->refresh();
        $esperado = round((float) $order->total * 18.5, 2);
        $this->assertSame('MXN', $order->payment_currency);
        $this->assertEqualsWithDelta($esperado, (float) $order->payment_amount, 0.02);
        $this->assertEqualsWithDelta($esperado, (float) $res->json('amount'), 0.02);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/checkout/preferences')
            && $r['items'][0]['currency_id'] === 'MXN'
            && abs($r['items'][0]['unit_price'] - $esperado) < 0.02
            && $r['external_reference'] === $order->id
            && str_contains($r['back_urls']['success'], '/checkout/success?order='.$order->id));

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'mercadopago', 'currency' => 'MXN', 'status' => 'pending']);
    }

    public function test_requires_the_order_token(): void
    {
        $this->fakeMp();
        $order = $this->pedido();

        $this->postJson('/api/v1/payments/mercadopago/create', ['order_id' => $order->id])->assertForbidden();
    }

    public function test_without_rate_no_preference_is_created(): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response([], 500),
            'open.er-api.com/*' => Http::response([], 500),
        ]);
        $order = $this->pedido();

        $this->crear($order)->assertStatus(503);
        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), 'mercadopago.com'));
    }

    public function test_is_unavailable_without_access_token(): void
    {
        config(['services.mercadopago.access_token' => null]);
        Cache::flush();
        $this->getJson('/api/v1/store/settings')->assertJsonPath('data.gateways.mercadopago', false);

        $this->postJson('/api/v1/payments/mercadopago/create', [])->assertStatus(503);
    }

    public function test_settings_expose_the_gateway_when_configured(): void
    {
        $this->getJson('/api/v1/store/settings')->assertJsonPath('data.gateways.mercadopago', true);
    }

    public function test_webhook_marks_paid_when_amount_covers_the_order(): void
    {
        $this->fakeMp(18.5);
        $order = $this->pedido();
        $this->crear($order)->assertOk();
        $order->refresh();

        $this->fakeMp(18.5, ['transaction_amount' => (float) $order->payment_amount, 'external_reference' => $order->id]);
        $this->webhook()->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertSent(OrderConfirmedMail::class);
    }

    public function test_webhook_does_not_mark_paid_if_amount_is_short_or_other_currency(): void
    {
        $this->fakeMp(18.5);
        $order = $this->pedido();
        $this->crear($order)->assertOk();
        $order->refresh();

        // Cobró el total en "USD" como si fueran pesos: 18 veces menos.
        $this->fakeMp(18.5, ['transaction_amount' => (float) $order->total, 'external_reference' => $order->id]);
        $this->webhook()->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->fakeMp(18.5, ['transaction_amount' => (float) $order->payment_amount, 'currency_id' => 'USD', 'external_reference' => $order->id]);
        $this->webhook()->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/mercadopago?data.id=1', [], ['x-signature' => 'ts=1,v1=malo', 'x-request-id' => 'x'])
            ->assertStatus(400);
        $this->assertSame(0, Payment::count());
    }
}
