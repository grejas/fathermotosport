<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VisorColor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El color de visor que el cliente elige para un casco viaja con el pedido. */
class OrderVisorColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function payload(array $items): array
    {
        return [
            'guest_email' => 'invitado@example.com',
            'items' => $items,
            'address' => [
                'full_name' => 'Cliente Prueba',
                'phone' => '76543210',
                'country' => 'Bolivia',
                'city' => 'Santa Cruz',
                'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
        ];
    }

    private function casco(int $stock = 5): ProductVariant
    {
        $product = Product::factory()->create(['weight' => 1, 'price' => 100, 'sale_price' => null]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => $stock]);
    }

    private function visor(string $name, bool $active = true): VisorColor
    {
        return VisorColor::create(['name' => $name, 'hex_color' => '#000000', 'is_active' => $active, 'sort_order' => 1]);
    }

    public function test_el_visor_elegido_queda_guardado_en_el_item_y_sale_en_la_api(): void
    {
        $variant = $this->casco();
        $iridium = $this->visor('Iridium azul');
        $variant->product->visorColors()->attach($iridium->id);

        $order = $this->postJson('/api/v1/orders', $this->payload([
            ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $iridium->id],
        ]))->assertCreated()->json('order');

        $item = OrderItem::where('order_id', $order['id'])->sole();
        $this->assertSame($iridium->id, $item->visor_color_id);
        $this->assertSame('Iridium azul', $item->visor_color);
        $this->assertSame('Iridium azul', $order['items'][0]['visor_color']);
    }

    public function test_sin_visores_propios_vale_uno_activo_del_catalogo_global(): void
    {
        $variant = $this->casco();
        $humo = $this->visor('Humo');

        $this->postJson('/api/v1/orders', $this->payload([
            ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $humo->id],
        ]))->assertCreated();

        $this->assertSame('Humo', OrderItem::sole()->visor_color);
    }

    public function test_rechaza_un_visor_que_no_es_del_casco_o_esta_inactivo(): void
    {
        $variant = $this->casco();
        $propio = $this->visor('Transparente');
        $ajeno = $this->visor('Dorado');
        $inactivo = $this->visor('Rojo', active: false);
        $variant->product->visorColors()->attach([$propio->id, $inactivo->id]);

        foreach ([$ajeno, $inactivo] as $visor) {
            $this->postJson('/api/v1/orders', $this->payload([
                ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $visor->id],
            ]))->assertUnprocessable()->assertJsonValidationErrors('items');
        }

        $this->assertSame(0, OrderItem::count());
    }

    public function test_sin_visor_el_pedido_sigue_funcionando(): void
    {
        $variant = $this->casco();

        $this->postJson('/api/v1/orders', $this->payload([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ]))->assertCreated();

        $this->assertNull(OrderItem::sole()->visor_color);
    }

    public function test_dos_lineas_del_mismo_casco_con_distinto_visor_suman_stock(): void
    {
        $variant = $this->casco(stock: 2);
        $a = $this->visor('Humo');
        $b = $this->visor('Iridium');

        $this->postJson('/api/v1/orders', $this->payload([
            ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $a->id],
            ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $b->id],
        ]))->assertCreated();
        $this->assertSame(2, OrderItem::count());

        $this->postJson('/api/v1/orders', $this->payload([
            ['variant_id' => $variant->id, 'quantity' => 2, 'visor_color_id' => $a->id],
            ['variant_id' => $variant->id, 'quantity' => 1, 'visor_color_id' => $b->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }
}
