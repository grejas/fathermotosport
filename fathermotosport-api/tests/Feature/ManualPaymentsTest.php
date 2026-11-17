<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PaymentController;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderReceivedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreConfig;
use App\Services\Payments\ManualPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Transferencia SEPA, USDT (TRC20 / BEP20) y BTC: el cliente paga por fuera con un monto
 * cotizado en vivo, y el equipo confirma el ingreso desde el panel.
 */
class ManualPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'ES9121000418450200051332';

    private const TRC20 = 'TXYZopYRdj2D9XRtbG411XZZ3kM5VkAeBf';

    private const BEP20 = '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3';

    private const BTC = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
    }

    private function configurar(): void
    {
        StoreConfig::current()->update([
            'sepa_beneficiary' => 'Fatherson Import',
            'sepa_iban' => self::IBAN,
            'sepa_bic' => 'CAIXESBBXXX',
            'sepa_bank_name' => 'CaixaBank',
            'usdt_trc20_address' => self::TRC20,
            'usdt_bep20_address' => self::BEP20,
            'btc_address' => self::BTC,
            'btc_bep20_address' => self::BEP20,
            'binance_pay_url' => 'https://app.binance.com/uni-qr/JvKpbhpc',
            'binance_pay_name' => 'fathersons Imp LLC',
        ]);
    }

    private function variante(int $stock = 5): ProductVariant
    {
        $product = Product::factory()->create(['price' => 215, 'sale_price' => null, 'weight' => 1]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => $stock]);
    }

    private function pedir(string $metodo, ?ProductVariant $variant = null)
    {
        $variant ??= $this->variante();

        return $this->postJson('/api/v1/orders', [
            'guest_email' => 'cliente@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '612345678', 'country' => 'España',
                'city' => 'Madrid', 'address_line' => 'Calle Prueba 1',
            ],
            'payment_method' => $metodo,
        ]);
    }

    private function fakeCotizaciones(float $eurPorUsd = 0.9, float $btcUsd = 100000): void
    {
        Http::fake([
            'api.frankfurter.app/*' => Http::response(['rates' => ['EUR' => $eurPorUsd]]),
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['usd' => $btcUsd]]),
        ]);
    }

    public function test_methods_are_hidden_until_configured(): void
    {
        $this->getJson('/api/v1/store/settings')
            ->assertJsonPath('data.payment_methods.sepa', false)
            ->assertJsonPath('data.payment_methods.btc', false);

        $this->pedir('sepa')->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, Order::count());

        $this->configurar();

        $this->getJson('/api/v1/store/settings')
            ->assertJsonPath('data.payment_methods.sepa', true)
            ->assertJsonPath('data.payment_methods.usdt_trc20', true)
            ->assertJsonPath('data.payment_methods.usdt_bep20', true)
            ->assertJsonPath('data.payment_methods.btc', true);
    }

    public function test_sepa_order_gets_live_eur_amount_and_instructions(): void
    {
        $this->configurar();
        $this->fakeCotizaciones(0.9);

        $res = $this->pedir('sepa')->assertCreated();

        // 430 USD × 0,9 EUR/USD = 387,00 EUR.
        $res->assertJsonPath('order.manual_payment.currency', 'EUR')
            ->assertJsonPath('order.manual_payment.amount', '387.00')
            ->assertJsonPath('order.manual_payment.iban', 'ES91 2100 0418 4502 0005 1332')
            ->assertJsonPath('order.manual_payment.beneficiary', 'Fatherson Import')
            ->assertJsonPath('order.manual_payment.reference', $res->json('order.order_number'))
            ->assertJsonPath('payment.next_step', 'manual');

        $order = Order::findOrFail($res->json('order.id'));
        $this->assertEqualsWithDelta(72, now()->diffInHours($order->payment_expires_at), 1);
        Mail::assertSent(OrderReceivedMail::class);
    }

    public function test_usdt_is_one_to_one_and_btc_rounds_up(): void
    {
        $this->configurar();
        $this->fakeCotizaciones(btcUsd: 100000);

        $this->pedir('usdt_trc20')->assertCreated()
            ->assertJsonPath('order.manual_payment.amount', '430.00')
            ->assertJsonPath('order.manual_payment.network', 'TRON (TRC20)')
            ->assertJsonPath('order.manual_payment.address', self::TRC20);

        $this->pedir('usdt_bep20')->assertCreated()
            ->assertJsonPath('order.manual_payment.address', self::BEP20);

        // 430 / 100000 = 0,0043 BTC exacto; nunca se redondea hacia abajo.
        $this->pedir('btc')->assertCreated()
            ->assertJsonPath('order.manual_payment.currency', 'BTC')
            ->assertJsonPath('order.manual_payment.amount', '0.00430000');

        // Bitcoin en BSC: misma cotización, dirección 0x… y red BEP20.
        $this->pedir('btc_bep20')->assertCreated()
            ->assertJsonPath('order.manual_payment.currency', 'BTC')
            ->assertJsonPath('order.manual_payment.amount', '0.00430000')
            ->assertJsonPath('order.manual_payment.network', 'BNB Smart Chain (BEP20)')
            ->assertJsonPath('order.manual_payment.address', self::BEP20);

        // Binance Pay: monto en USDT 1:1, enlace del QR y nombre de la cuenta.
        $this->pedir('binance_pay')->assertCreated()
            ->assertJsonPath('order.manual_payment.currency', 'USDT')
            ->assertJsonPath('order.manual_payment.amount', '430.00')
            ->assertJsonPath('order.manual_payment.pay_url', 'https://app.binance.com/uni-qr/JvKpbhpc')
            ->assertJsonPath('order.manual_payment.merchant_name', 'fathersons Imp LLC')
            ->assertJsonPath('order.manual_payment.accepted_currencies', ['USDT', 'USDC', 'BTC']);

        $this->assertSame('0.00430001', ManualPaymentService::montoAPagar(430.0001, ['moneda' => 'BTC', 'tasa' => 100000]));
    }

    public function test_falls_back_to_second_source_and_refuses_without_quote(): void
    {
        $this->configurar();

        Http::fake([
            'api.frankfurter.app/*' => Http::response('caído', 500),
            'open.er-api.com/*' => Http::response(['rates' => ['EUR' => 0.8]]),
        ]);
        $this->pedir('sepa')->assertCreated()->assertJsonPath('order.manual_payment.amount', '344.00');

        Cache::flush();
        Http::fake([
            'api.coingecko.com/*' => Http::response('caído', 500),
            'api.kraken.com/*' => Http::response('caído', 500),
        ]);
        $antes = Order::count();
        $this->pedir('btc')->assertStatus(422)->assertJsonValidationErrors('payment_method');
        $this->assertSame($antes, Order::count(), 'sin cotización no se crea el pedido');
    }

    public function test_manual_orders_wait_for_their_own_expiry_before_cancelling(): void
    {
        $this->configurar();
        $this->fakeCotizaciones();

        $id = $this->pedir('sepa')->assertCreated()->json('order.id');
        DB::table('orders')->where('id', $id)->update(['created_at' => now()->subHours(3)]);

        $this->artisan('orders:cancel-abandoned', ['--apply' => true, '--minutes' => 30])->assertSuccessful();
        $this->assertSame('pending', Order::find($id)->status, 'una transferencia no se cancela a los 30 minutos');

        DB::table('orders')->where('id', $id)->update(['payment_expires_at' => now()->subMinute()]);
        $this->artisan('orders:cancel-abandoned', ['--apply' => true, '--minutes' => 30])->assertSuccessful();
        $this->assertSame('cancelled', Order::find($id)->status);
    }

    public function test_admin_confirmation_marks_paid_once_and_rejects_reused_reference(): void
    {
        $this->configurar();
        $this->fakeCotizaciones();
        $variant = $this->variante(5);

        $order = Order::findOrFail($this->pedir('usdt_trc20', $variant)->json('order.id'));
        $otro = Order::findOrFail($this->pedir('usdt_trc20', $variant)->json('order.id'));

        $controller = app(PaymentController::class);
        $controller->confirmarPagoManual($order, 'abc123hash');
        $controller->confirmarPagoManual($order, 'abc123hash'); // doble clic: no repite nada

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(3, $variant->fresh()->stock, 'stock descontado una sola vez');
        Mail::assertSent(OrderConfirmedMail::class, 1);

        $this->expectException(\InvalidArgumentException::class);
        $controller->confirmarPagoManual($otro, 'abc123hash');
    }

    public function test_panel_validators(): void
    {
        $this->assertTrue(ManualPaymentService::ibanValido('ES91 2100 0418 4502 0005 1332'));
        $this->assertFalse(ManualPaymentService::ibanValido('ES92 2100 0418 4502 0005 1332'));
        $this->assertTrue(ManualPaymentService::bicValido('CAIXESBBXXX'));
        $this->assertTrue(ManualPaymentService::direccionValida('usdt_trc20', self::TRC20));
        $this->assertFalse(ManualPaymentService::direccionValida('usdt_trc20', self::BEP20));
        $this->assertTrue(ManualPaymentService::direccionValida('usdt_bep20', self::BEP20));
        $this->assertTrue(ManualPaymentService::direccionValida('btc', self::BTC));
        $this->assertFalse(ManualPaymentService::direccionValida('btc', self::TRC20));
        $this->assertTrue(ManualPaymentService::direccionValida('btc_bep20', self::BEP20));
        $this->assertFalse(ManualPaymentService::direccionValida('btc_bep20', self::BTC));
        $this->assertTrue(ManualPaymentService::direccionValida('binance_pay', 'https://app.binance.com/uni-qr/JvKpbhpc'));
        $this->assertFalse(ManualPaymentService::direccionValida('binance_pay', 'https://binance.com.phishing.io/uni-qr/x'));
        $this->assertFalse(ManualPaymentService::direccionValida('binance_pay', 'javascript:alert(1)'));
    }

    public function test_lists_stay_in_sync(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(ManualPaymentService::METODOS),
            Order::METODOS_CON_COORDINACION_MANUAL,
        );
    }
}
