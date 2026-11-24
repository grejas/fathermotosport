<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\MetaConversions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Compra enviada a Meta desde el servidor cuando el pedido pasa a pagado. */
class MetaConversionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Cache::flush();
        config([
            'services.meta.pixel_id' => '3627895500705570',
            'services.meta.capi_token' => 'token-de-prueba',
            'services.meta.test_event_code' => null,
        ]);
    }

    private function pedido(): Order
    {
        $product = Product::factory()->create(['price' => 215, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $id = $this->postJson('/api/v1/orders', [
            'guest_email' => 'Cliente@Example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'José Pérez', 'phone' => '+591 765-43210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ])->assertCreated()->json('order.id');

        return Order::findOrFail($id);
    }

    public function test_al_marcar_pagado_envia_purchase_con_el_mismo_event_id_que_el_navegador(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $order = $this->pedido();

        $order->update(['payment_status' => 'paid']);
        // Los trabajos "después de responder" corren al terminar la app.
        app()->terminate();

        Http::assertSent(function (Request $r) use ($order) {
            if (! str_contains($r->url(), 'graph.facebook.com/v21.0/3627895500705570/events')) {
                return false;
            }
            $e = $r['data'][0];

            return $e['event_name'] === 'Purchase'
                && $e['event_id'] === "pay-{$order->id}"
                && $e['action_source'] === 'website'
                && $e['custom_data']['currency'] === 'USD'
                && (float) $e['custom_data']['value'] === round((float) $order->total, 2)
                && $e['custom_data']['num_items'] === 2
                && $e['user_data']['em'] === [hash('sha256', 'cliente@example.com')]
                && $e['user_data']['ph'] === [hash('sha256', '59176543210')]
                && $e['user_data']['fn'] === [hash('sha256', 'jose')]
                && $e['user_data']['country'] === [hash('sha256', 'bo')];
        });
    }

    public function test_no_envia_nada_si_el_pedido_no_queda_pagado(): void
    {
        Http::fake();
        $order = $this->pedido();

        $order->update(['status' => 'processing']);
        app()->terminate();

        Http::assertNothingSent();
    }

    public function test_sin_token_no_envia(): void
    {
        config(['services.meta.capi_token' => null]);
        Http::fake();
        $order = $this->pedido();

        $order->update(['payment_status' => 'paid']);
        app()->terminate();

        Http::assertNothingSent();
    }

    public function test_un_error_de_meta_no_rompe_nada(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);
        $order = $this->pedido();

        $this->assertFalse(app(MetaConversions::class)->sendPurchase($order));
    }
}
