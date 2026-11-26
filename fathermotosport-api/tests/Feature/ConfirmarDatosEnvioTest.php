<?php

namespace Tests\Feature;

use App\Mail\ConfirmarDatosEnvioMail;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VisorColor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Correo para confirmar visor, talla y dirección: siempre desde ventas@, nunca personal. */
class ConfirmarDatosEnvioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function pedido(?string $visor = null, string $locale = 'es'): Order
    {
        $cat = Category::where('slug', 'cascos')->firstOrFail();
        $product = Product::factory()->create(['category_id' => $cat->id, 'name' => 'Casco AGV Prueba', 'slug' => 'casco-agv-prueba']);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 3]);
        $humo = VisorColor::create(['name' => 'Humo', 'hex_color' => '#333', 'is_active' => true, 'sort_order' => 1]);
        $rojo = VisorColor::create(['name' => 'Iridium Red', 'hex_color' => '#f00', 'is_active' => true, 'sort_order' => 2]);
        $product->visorColors()->attach([$humo->id, $rojo->id]);

        $address = Address::create(['full_name' => "Oscar O'Neil", 'phone' => '6462329498', 'country' => 'Estados Unidos',
            'city' => 'Bronx', 'state' => 'NY', 'address_line' => '3050 Decatur Ave', 'reference' => 'Apt 5E']);
        $order = Order::create(['address_id' => $address->id, 'guest_email' => 'cliente@example.com', 'status' => 'processing',
            'subtotal' => 430, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'total' => 430, 'payment_status' => 'paid',
            'shipping_status' => 'pending', 'payment_method' => 'stripe', 'country' => 'Estados Unidos', 'locale' => $locale]);
        OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 430,
            'subtotal' => 430, 'size' => 'M', 'visor_color' => $visor]);

        return $order;
    }

    public function test_sale_de_ventas_con_respuesta_a_ventas_y_lleva_visores_talla_y_direccion(): void
    {
        Mail::fake();
        $order = $this->pedido();

        $this->artisan('pedidos:pedir-datos', ['pedido' => [$order->order_number]])->assertSuccessful();

        Mail::assertSent(ConfirmarDatosEnvioMail::class, function (ConfirmarDatosEnvioMail $mail) {
            $texto = $mail->render();

            return $mail->hasTo('cliente@example.com')
                && $mail->hasFrom('ventas@fathermotosport.com')
                && $mail->hasReplyTo('ventas@fathermotosport.com')
                && ! str_contains($texto, 'gmail.com')
                && str_contains($texto, "- Humo\n- Iridium Red")
                && str_contains($texto, 'Talla – Casco AGV Prueba: M')
                && str_contains($texto, "Recipient Name: Oscar O'Neil")
                && str_contains($texto, 'Recipient Address: 3050 Decatur Ave, Apt 5E')
                && str_contains($texto, 'Recipient Province/State: NY')
                && str_contains($texto, 'Recipient Postal Code: (falta')
                && str_contains($texto, '/product/casco-agv-prueba');
        });
    }

    public function test_si_ya_eligio_visor_lo_muestra_y_respeta_el_idioma(): void
    {
        $order = $this->pedido('Humo', 'en');
        $texto = (new ConfirmarDatosEnvioMail($order))->render();

        $this->assertStringContainsString('you chose: Humo', $texto);
        $this->assertStringContainsString('Your order', (new ConfirmarDatosEnvioMail($order))->envelope()->subject);
    }

    public function test_ver_no_envia_nada(): void
    {
        Mail::fake();
        $order = $this->pedido();

        $this->artisan('pedidos:pedir-datos', ['pedido' => [$order->order_number], '--ver' => true])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_la_nota_va_al_inicio(): void
    {
        $texto = (new ConfirmarDatosEnvioMail($this->pedido(), 'Este es nuestro correo oficial de ventas.'))->render();

        $this->assertStringContainsString("Hola Oscar,\n\nEste es nuestro correo oficial de ventas.", $texto);
    }
}
