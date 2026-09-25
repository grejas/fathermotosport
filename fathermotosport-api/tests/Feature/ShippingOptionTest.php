<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use App\Services\ShippingWeightService;
use App\Support\ShippingCountries;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShippingOptionTest extends TestCase
{
    use RefreshDatabase;

    private function option(array $attributes = []): ShippingOption
    {
        return ShippingOption::create(array_merge([
            'country_code' => 'BO',
            'method_name' => 'Estándar',
            'min_weight_kg' => 0,
            'max_weight_kg' => 5,
            'price' => 0,
            'currency' => 'USD',
            'estimated_days_min' => 20,
            'estimated_days_max' => 30,
            'is_active' => true,
        ], $attributes));
    }

    public function test_available_for_returns_every_method_of_the_country_ordered_by_price(): void
    {
        $estandar = $this->option(['method_name' => 'Estándar', 'price' => 0]);
        $express = $this->option(['method_name' => 'Express DHL', 'price' => 25, 'estimated_days_min' => 5, 'estimated_days_max' => 7]);
        $this->option(['country_code' => 'BR', 'method_name' => 'Estándar', 'price' => 10]);

        $options = ShippingOption::availableFor('BO', 3);

        $this->assertSame([$estandar->id, $express->id], $options->pluck('id')->all());
        $this->assertSame(['Estándar', 'Express DHL'], $options->pluck('method_name')->all());
    }

    public function test_available_for_respects_weight_ranges_and_active_flag(): void
    {
        $liviano = $this->option(['method_name' => 'Estándar', 'min_weight_kg' => 0, 'max_weight_kg' => 2, 'price' => 8]);
        $pesado = $this->option(['method_name' => 'Estándar', 'min_weight_kg' => 2, 'max_weight_kg' => null, 'price' => 20]);
        $this->option(['method_name' => 'Express DHL', 'price' => 25, 'is_active' => false]);

        // Una sola fila por método: en 1 kg gana la liviana; en 40 kg la de "2 kg en adelante".
        $this->assertSame([$liviano->id], ShippingOption::availableFor('BO', 1)->pluck('id')->all());
        $this->assertSame([$pesado->id], ShippingOption::availableFor('BO', 40)->pluck('id')->all());
        // En el borde (2 kg) gana el rango más ajustado.
        $this->assertSame([$pesado->id], ShippingOption::availableFor('BO', 2)->pluck('id')->all());
        // Código de país sin distinguir mayúsculas.
        $this->assertCount(1, ShippingOption::availableFor('bo', 1));
    }

    public function test_endpoint_returns_all_options_for_the_country(): void
    {
        $this->option(['method_name' => 'Estándar', 'price' => 0]);
        $this->option(['method_name' => 'Express DHL', 'price' => 25, 'estimated_days_min' => 5, 'estimated_days_max' => 7]);

        $this->getJson('/api/v1/shipping/calculate?country_code=BO&weight_kg=3.2')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('country_name', 'Bolivia')
            ->assertJsonPath('weight_kg', 3.2)
            ->assertJsonCount(2, 'options')
            ->assertJsonPath('options.0.method_name', 'Estándar')
            ->assertJsonPath('options.0.price', '0.00')
            ->assertJsonPath('options.1.method_name', 'Express DHL')
            ->assertJsonPath('options.1.price', '25.00')
            ->assertJsonPath('options.1.estimated_days_max', 7);
    }

    public function test_endpoint_returns_a_clear_message_when_no_option_matches(): void
    {
        $this->option(['min_weight_kg' => 0, 'max_weight_kg' => 5]);

        foreach (['BR', 'ZZ'] as $country) {
            $this->getJson("/api/v1/shipping/calculate?country_code={$country}&weight_kg=1")
                ->assertOk()
                ->assertJsonPath('available', false)
                ->assertJsonCount(0, 'options');
        }

        // Peso fuera de todos los rangos del país.
        $this->getJson('/api/v1/shipping/calculate?country_code=BO&weight_kg=80')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_endpoint_validates_input(): void
    {
        $this->getJson('/api/v1/shipping/calculate')->assertStatus(422);
        $this->getJson('/api/v1/shipping/calculate?country_code=BOL&weight_kg=1')->assertStatus(422);
        $this->getJson('/api/v1/shipping/calculate?country_code=BO&weight_kg=-3')->assertStatus(422);
    }

    public function test_weight_service_uses_product_weight_and_logs_products_without_weight(): void
    {
        $this->seed(DatabaseSeeder::class);

        $conPeso = Product::factory()->create(['weight' => 2.5, 'sku' => 'FMS-CON-PESO']);
        $sinPeso = Product::factory()->create(['weight' => null, 'sku' => 'FMS-SIN-PESO', 'name' => 'Casco Sin Peso']);
        $v1 = ProductVariant::factory()->for($conPeso)->create(['size' => 'M']);
        $v2 = ProductVariant::factory()->for($sinPeso)->create(['size' => 'L']);

        Log::spy();

        $total = app(ShippingWeightService::class)->forItems([
            ['variant_id' => $v1->id, 'quantity' => 2],
            ['variant_id' => $v2->id, 'quantity' => 3],
        ]);

        // 2 × 2.5 kg reales + 3 × 1 kg por defecto.
        $this->assertSame(8.0, $total);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'sin peso')
                && $context['productos'] === ['FMS-SIN-PESO — Casco Sin Peso']);
    }

    public function test_endpoint_calculates_weight_from_cart_items_when_given(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->option(['method_name' => 'Express DHL', 'min_weight_kg' => 5, 'max_weight_kg' => null, 'price' => 42]);

        $product = Product::factory()->create(['weight' => 3]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M']);

        $this->getJson("/api/v1/shipping/calculate?country_code=BO&items={$variant->id}:2")
            ->assertOk()
            ->assertJsonPath('weight_kg', 6)
            ->assertJsonPath('options.0.price', '42.00');
    }

    /** @return array{0: ProductVariant, 1: array} */
    private function orderPayload(array $overrides = []): array
    {
        // sale_price explícito: la factory lo calcula al azar y taparía el precio de 100.
        $product = Product::factory()->create(['weight' => 1, 'price' => 100, 'sale_price' => null]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        return [$variant, array_merge([
            'guest_email' => 'cliente@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'address' => [
                'full_name' => 'Cliente Prueba',
                'phone' => '76543210',
                'country' => 'Bolivia',
                'city' => 'Santa Cruz',
                'address_line' => 'Av. Siempre Viva 123',
            ],
            'payment_method' => 'paypal',
        ], $overrides)];
    }

    public function test_order_saves_the_chosen_option_and_adds_it_to_the_total(): void
    {
        $this->seed(DatabaseSeeder::class);
        $express = $this->option(['method_name' => 'Express DHL', 'min_weight_kg' => 0, 'max_weight_kg' => null, 'price' => 25]);

        [, $payload] = $this->orderPayload([
            'shipping_option_id' => $express->id,
            'shipping_country_code' => 'BO',
        ]);

        $this->postJson('/api/v1/orders', $payload)->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('25.00', $order->shipping);
        $this->assertSame('225.00', $order->total);
        $this->assertSame($express->id, $order->shipping_option_id);
        $this->assertSame('Express DHL', $order->shipping_method_name);
    }

    public function test_order_rejects_an_option_that_does_not_apply_to_that_country(): void
    {
        $this->seed(DatabaseSeeder::class);
        $otroPais = $this->option(['country_code' => 'BR', 'method_name' => 'Express DHL', 'price' => 25]);

        [, $payload] = $this->orderPayload([
            'shipping_option_id' => $otroPais->id,
            'shipping_country_code' => 'BO',
        ]);

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_option_id');

        $this->assertSame(0, Order::count());
    }

    public function test_shipping_countries_list_is_the_fixed_40_and_matches_the_frontend(): void
    {
        $options = ShippingCountries::options();

        $this->assertCount(40, $options);
        $this->assertCount(20, ShippingCountries::AMERICAS);
        $this->assertCount(20, ShippingCountries::EUROPE);
        $this->assertSame('Bolivia', ShippingCountries::name('BO'));
        $this->assertSame('España', ShippingCountries::name('es'));
        $this->assertFalse(ShippingCountries::isValid('JP'));

        // La lista del checkout (TypeScript) debe tener exactamente los mismos códigos.
        $ts = file_get_contents(base_path('../fathermotosport-web/lib/data/shippingCountries.ts'));
        preg_match_all('/code: "([A-Z]{2})"/', $ts, $matches);

        $this->assertEqualsCanonicalizing(array_keys($options), $matches[1]);
    }

    public function test_order_without_shipping_option_keeps_shipping_at_zero(): void
    {
        $this->seed(DatabaseSeeder::class);
        [, $payload] = $this->orderPayload();

        $this->postJson('/api/v1/orders', $payload)->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame('0.00', $order->shipping);
        $this->assertSame('200.00', $order->total);
        $this->assertNull($order->shipping_method_name);
    }
}
