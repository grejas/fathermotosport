<?php

namespace Tests\Feature;

use App\Filament\Resources\UpsellRuleResource\Pages\CreateUpsellRule;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mejoras del upsell (24-nov-2026):
 * - Cobro en un toque con la tarjeta guardada del pedido original.
 * - Ofertas tras 3-D Secure: el pago pendiente se informa para que el navegador reintente.
 * - "Combina con tu …" en cada oferta.
 * - Rebaja opcional en el último aviso, calculada y validada en el servidor.
 * - Reglas pedidas por Gerson: visera al 50 %, mangas al 30 %, segundo casco al 20 %.
 */
class UpsellMejorasTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que responde Stripe al cobrar con la tarjeta guardada. */
    private array $cobro = ['status' => 'succeeded'];

    /** Código HTTP de ese cobro (402 = tarjeta rechazada). */
    private int $cobroHttp = 200;

    /** El intent del pago original guardó la tarjeta. */
    private bool $tarjetaGuardada = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_1']);
            }

            // Relectura del intent del pedido original (con el método de pago expandido).
            if ($request->method() === 'GET' && str_contains($url, 'pi_original')) {
                return Http::response([
                    'id' => 'pi_original',
                    'status' => 'succeeded',
                    'customer' => 'cus_test_1',
                    'payment_method_options' => ['card' => ['setup_future_usage' => $this->tarjetaGuardada ? 'on_session' : null]],
                    'payment_method' => [
                        'id' => 'pm_test_1',
                        'type' => 'card',
                        'customer' => $this->tarjetaGuardada ? 'cus_test_1' : null,
                        'card' => ['brand' => 'visa', 'last4' => '4242'],
                    ],
                ]);
            }

            // Cobro de la venta cruzada.
            if ($request->method() === 'POST' && str_contains($url, '/v1/payment_intents')) {
                if ($this->cobroHttp !== 200) {
                    return Http::response(['error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds']], $this->cobroHttp);
                }

                return Http::response(array_merge([
                    'id' => 'pi_upsell',
                    'client_secret' => 'pi_upsell_secret',
                    'amount' => (int) $request['amount'],
                    'amount_received' => (int) $request['amount'],
                ], $this->cobro));
            }

            return Http::response(['id' => 'pi_upsell', 'status' => 'succeeded']);
        });
    }

    private function producto(float $precio = 100, ?string $talla = 'M', ?string $nombre = null): ProductVariant
    {
        $product = Product::factory()->create(['price' => $precio, 'sale_price' => null, 'weight' => 1] + ($nombre ? ['name' => $nombre] : []));
        $product->variants()->delete();

        return ProductVariant::factory()->for($product)->create(['size' => $talla, 'stock' => 5]);
    }

    private function regla(ProductVariant $disparador, ProductVariant $ofrecido, array $extra = []): UpsellRule
    {
        return UpsellRule::create(array_merge([
            'trigger_product_id' => $disparador->product_id,
            'offer_product_id' => $ofrecido->product_id,
            'discount_percent' => 20,
            'priority' => 0,
            'is_active' => true,
        ], $extra));
    }

    private function pedido(ProductVariant $variant, string $pago = 'paid'): Order
    {
        $order = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order');

        $modelo = Order::findOrFail($order['id']);

        if ($pago === 'paid') {
            $modelo->update(['payment_status' => 'paid', 'status' => 'processing']);
            Payment::create([
                'order_id' => $modelo->id, 'provider' => 'stripe', 'transaction_id' => 'pi_original',
                'currency' => 'USD', 'amount' => $modelo->total, 'status' => 'approved', 'paid_at' => now(),
            ]);
        }

        return $modelo->fresh();
    }

    private function cabeceras(Order $order): array
    {
        return ['X-Order-Token' => $order->access_token];
    }

    /** Crea el pedido de venta cruzada y lo devuelve. */
    private function upsell(Order $original, UpsellRule $regla, ProductVariant $variante, bool $ultimaOportunidad = false): Order
    {
        $datos = ['items' => [['rule_id' => $regla->id, 'variant_id' => $variante->id]]];
        if ($ultimaOportunidad) {
            $datos['last_chance'] = true;
        }

        $id = $this->postJson("/api/v1/orders/{$original->id}/upsell", $datos, $this->cabeceras($original))
            ->assertCreated()
            ->json('order.id');

        return Order::findOrFail($id);
    }

    // ───────── 3-D Secure: pago pendiente ─────────

    public function test_a_pending_payment_is_reported_so_the_browser_retries(): void
    {
        $casco = $this->producto();
        $this->regla($casco, $this->producto(50));
        $order = $this->pedido($casco, 'pending');

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertExactJson(['offers' => [], 'pending' => true]);

        // No arranca la ventana de la oferta: todavía no se mostró nada.
        $this->assertNull($order->fresh()->upsell_offered_at);
    }

    public function test_a_cancelled_unpaid_order_is_not_reported_as_pending(): void
    {
        $casco = $this->producto();
        $order = $this->pedido($casco, 'pending');
        $order->update(['status' => 'cancelled']);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonMissing(['pending' => true]);
    }

    // ───────── Motivo y tarjeta guardada en la respuesta ─────────

    public function test_each_offer_says_what_it_goes_with_and_the_saved_card(): void
    {
        $casco = $this->producto(500, 'M', 'Casco AGV Pista GP R');
        $this->regla($casco, $this->producto(50));
        $order = $this->pedido($casco);

        $respuesta = $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))->assertOk();

        $respuesta->assertJsonPath('offers.0.pairs_with', 'Casco AGV Pista GP R')
            ->assertJsonPath('offers.0.same_kind', false)
            ->assertJsonPath('saved_card', ['brand' => 'visa', 'last4' => '4242']);

        // Los ids de Stripe no salen del servidor.
        $this->assertStringNotContainsString('pm_test_1', $respuesta->getContent());
        $this->assertStringNotContainsString('cus_test_1', $respuesta->getContent());
    }

    public function test_no_saved_card_when_the_original_payment_did_not_keep_it(): void
    {
        $this->tarjetaGuardada = false;
        $casco = $this->producto();
        $this->regla($casco, $this->producto(50));
        $order = $this->pedido($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonPath('saved_card', null);
    }

    public function test_a_second_helmet_is_flagged_as_same_kind(): void
    {
        $cascos = Category::where('slug', 'cascos')->firstOrFail();
        $casco = $this->producto(400, 'M', 'Casco A');
        $casco->product->update(['category_id' => $cascos->id]);
        $otro = $this->producto(300, 'L', 'Casco B');
        $otro->product->update(['category_id' => $cascos->id]);
        UpsellRule::create([
            'trigger_category_id' => $cascos->id, 'offer_category_id' => $cascos->id,
            'discount_percent' => 20, 'priority' => 0, 'is_active' => true,
        ]);
        $order = $this->pedido($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertOk()
            ->assertJsonPath('offers.0.pairs_with', 'Casco A')
            ->assertJsonPath('offers.0.same_kind', true);
    }

    // ───────── Rebaja del último aviso ─────────

    public function test_the_last_chance_price_is_offered_and_charged_only_when_asked(): void
    {
        $casco = $this->producto();
        $mangas = $this->producto(100, null);
        $regla = $this->regla($casco, $mangas, ['discount_percent' => 20, 'last_chance_discount_percent' => 35]);
        $order = $this->pedido($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertJsonPath('offers.0.discounted_price', '80.00')
            ->assertJsonPath('offers.0.last_chance_percent', 35)
            ->assertJsonPath('offers.0.last_chance_price', '65.00');

        $this->assertSame('80.00', $this->upsell($order, $regla, $mangas)->total);
        $this->assertSame('65.00', $this->upsell($order, $regla, $mangas, true)->total);
    }

    public function test_without_a_configured_rebate_the_last_chance_flag_changes_nothing(): void
    {
        $casco = $this->producto();
        $mangas = $this->producto(100, null);
        // Una "rebaja" menor que el descuento normal no cuenta como rebaja.
        $regla = $this->regla($casco, $mangas, ['discount_percent' => 20, 'last_chance_discount_percent' => 10]);
        $order = $this->pedido($casco);

        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertJsonPath('offers.0.last_chance_price', null);

        $this->assertSame('80.00', $this->upsell($order, $regla, $mangas, true)->total);
    }

    public function test_the_panel_refuses_a_rebate_not_above_the_normal_discount(): void
    {
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $base = [
            'trigger_tipo' => 'producto', 'offer_tipo' => 'producto',
            'trigger_product_id' => $casco->product_id, 'offer_product_ids' => [$mangas->product_id],
            'discount_percent' => 20, 'priority' => 0, 'is_active' => true,
        ];

        Livewire::test(CreateUpsellRule::class)
            ->fillForm($base + ['last_chance_discount_percent' => 20])
            ->call('create')
            ->assertHasFormErrors(['last_chance_discount_percent']);
        $this->assertSame(0, UpsellRule::count());

        Livewire::test(CreateUpsellRule::class)
            ->fillForm($base + ['last_chance_discount_percent' => 35])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(35, UpsellRule::firstOrFail()->last_chance_discount_percent);
    }

    // ───────── Cobro en un toque ─────────

    public function test_the_upsell_is_charged_with_the_saved_card_in_one_tap(): void
    {
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id], $this->cabeceras($upsell))
            ->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('client_secret', null);

        $this->assertSame('paid', $upsell->fresh()->payment_status);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v1/payment_intents')
            && $request['customer'] === 'cus_test_1'
            && $request['payment_method'] === 'pm_test_1'
            && $request['amount'] === 4000
            && $request->hasHeader('Idempotency-Key', "upsell-{$upsell->id}-4000"));
    }

    public function test_when_the_bank_asks_for_3ds_the_browser_gets_the_client_secret(): void
    {
        $this->cobro = ['status' => 'requires_action'];
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id], $this->cabeceras($upsell))
            ->assertOk()
            ->assertJsonPath('status', 'requires_action')
            ->assertJsonPath('client_secret', 'pi_upsell_secret');

        $this->assertSame('pending', $upsell->fresh()->payment_status);
    }

    public function test_a_declined_card_asks_for_another_payment_method(): void
    {
        $this->cobroHttp = 402;
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id], $this->cabeceras($upsell))
            ->assertStatus(402)
            ->assertJsonPath('sin_tarjeta', true);

        $this->assertSame('pending', $upsell->fresh()->payment_status);
    }

    public function test_the_saved_card_cannot_be_used_on_a_normal_order(): void
    {
        $casco = $this->producto();
        $order = $this->pedido($casco, 'pending');

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $order->id], $this->cabeceras($order))
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_the_saved_card_requires_the_order_token(): void
    {
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id])
            ->assertStatus(403);

        // El token del ORIGINAL tampoco alcanza: cada pedido tiene el suyo.
        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id], $this->cabeceras($original))
            ->assertStatus(403);
    }

    public function test_without_a_saved_card_the_charge_is_refused_cleanly(): void
    {
        $this->tarjetaGuardada = false;
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);

        $this->postJson('/api/v1/payments/stripe/saved-card', ['order_id' => $upsell->id], $this->cabeceras($upsell))
            ->assertStatus(409)
            ->assertJsonPath('sin_tarjeta', true);

        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/v1/payment_intents'));
    }

    // ───────── Guardar la tarjeta en el pago original ─────────

    public function test_a_normal_order_intent_saves_the_card_and_an_upsell_intent_does_not(): void
    {
        $casco = $this->producto();
        $mangas = $this->producto(50, null);
        $regla = $this->regla($casco, $mangas);
        $normal = $this->pedido($casco, 'pending');

        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $normal->id], $this->cabeceras($normal))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/payment_intents')
            && $request->method() === 'POST'
            && $request['customer'] === 'cus_test_1'
            && ($request['payment_method_options']['card']['setup_future_usage'] ?? null) === 'on_session');

        $original = $this->pedido($casco);
        $upsell = $this->upsell($original, $regla, $mangas);
        $this->postJson('/api/v1/payments/stripe/intent', ['order_id' => $upsell->id], $this->cabeceras($upsell))->assertOk();

        // El intent del upsell (pagado con tarjeta nueva) no crea Customer ni guarda nada.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/payment_intents')
            && $request->method() === 'POST'
            && ($request['metadata']['order_id'] ?? null) === $upsell->id
            && ! isset($request['customer'])
            && ! isset($request['payment_method_options']));
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), '/v1/customers')));
    }

    // ───────── Reglas cargadas por migración ─────────

    public function test_the_migration_loads_visor_sleeves_and_second_helmet_rules(): void
    {
        $cascos = Category::where('slug', 'cascos')->firstOrFail();
        $pista = $this->producto(430, 'M', 'Casco AGV Pista GP R Soleluna');
        $pista->product->update(['category_id' => $cascos->id]);
        $shoei = $this->producto(430, 'M', 'Casco Shoei X-Fifteen');
        $shoei->product->update(['category_id' => $cascos->id]);
        $visera = $this->producto(69.99, null, 'Visor Pista AGV GP R/GP RR/Corsa R');
        $mangas = $this->producto(45, null, 'Manga Dainese Negro');

        $migracion = require database_path('migrations/2026_11_24_100002_reglas_upsell_visera_mangas_segundo_casco.php');
        $migracion->up();
        $migracion->up(); // Idempotente.

        $this->assertDatabaseHas('upsell_rules', ['trigger_product_id' => $pista->product_id, 'offer_product_id' => $visera->product_id, 'discount_percent' => 50]);
        $this->assertDatabaseMissing('upsell_rules', ['trigger_product_id' => $shoei->product_id, 'offer_product_id' => $visera->product_id]);
        $this->assertDatabaseHas('upsell_rules', ['trigger_category_id' => $cascos->id, 'offer_product_id' => $mangas->product_id, 'discount_percent' => 30]);
        $this->assertDatabaseHas('upsell_rules', ['trigger_category_id' => $cascos->id, 'offer_category_id' => $cascos->id, 'discount_percent' => 20]);
        $this->assertSame(1, UpsellRule::where('trigger_product_id', $pista->product_id)->count());

        // El comprador del Pista ve la visera primero, a mitad de precio.
        $order = $this->pedido($pista);
        $this->getJson("/api/v1/orders/{$order->id}/upsell", $this->cabeceras($order))
            ->assertJsonPath('offers.0.product.id', $visera->product_id)
            ->assertJsonPath('offers.0.discounted_price', '35.00');
    }
}
