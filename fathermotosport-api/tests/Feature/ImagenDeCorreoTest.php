<?php

namespace Tests\Feature;

use App\Mail\PaymentRecoveryMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ImagenDeCorreo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * URLs de imágenes de correo: Gmail no muestra una imagen cuya URL lleva espacios sin
 * codificar ("Black Converse Pants.png"), y una imagen que no existe o en un formato
 * que no soporta queda como un recuadro roto.
 */
class ImagenDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.fathermotosport.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Como en producción: el disco public se sirve desde la API, por https.
        config(['app.url' => self::API, 'filesystems.disks.public.url' => self::API.'/storage']);
        URL::forceRootUrl(self::API);
        Storage::fake('public');
    }

    // ───────── Codificación ─────────

    public function test_spaces_in_the_file_name_are_encoded(): void
    {
        $this->assertSame(
            self::API.'/storage/products/6ac5143149ec1_Black%20Converse%20Pants%20on%20White%20Background.png',
            ImagenDeCorreo::url(self::API.'/storage/products/6ac5143149ec1_Black Converse Pants on White Background.png')
        );
    }

    public function test_an_already_encoded_url_is_not_encoded_twice(): void
    {
        $codificada = self::API.'/storage/products/6ac5143149ec1_Black%20Converse%20Pants%20on%20White%20Background.png';

        $this->assertSame($codificada, ImagenDeCorreo::url($codificada));
        $this->assertStringNotContainsString('%2520', ImagenDeCorreo::url($codificada));
    }

    public function test_accents_are_encoded_once_whether_or_not_they_came_encoded(): void
    {
        $esperada = self::API.'/storage/products/Casco%20Az%C3%BAl%20%C3%91and%C3%BA.png';

        $this->assertSame($esperada, ImagenDeCorreo::url(self::API.'/storage/products/Casco Azúl Ñandú.png'));
        $this->assertSame($esperada, ImagenDeCorreo::url($esperada));
    }

    public function test_the_folders_are_encoded_too_and_the_slashes_are_kept(): void
    {
        $this->assertSame(
            self::API.'/storage/productos%20nuevos/2026/casco%20rojo.jpg',
            ImagenDeCorreo::url(self::API.'/storage/productos nuevos/2026/casco rojo.jpg')
        );
    }

    public function test_the_url_ends_up_absolute_and_https(): void
    {
        // Relativa: se completa con APP_URL.
        $this->assertSame(self::API.'/storage/products/a%20b.png', ImagenDeCorreo::url('storage/products/a b.png'));
        // http de un host público: pasa a https.
        $this->assertSame('https://cdn.example.com/a%20b.png', ImagenDeCorreo::url('http://cdn.example.com/a b.png'));
        // La query se respeta.
        $this->assertSame('https://cdn.example.com/a.png?v=2', ImagenDeCorreo::url('https://cdn.example.com/a.png?v=2'));
        // En desarrollo local no hay certificado: se queda en http.
        $this->assertSame('http://localhost:8000/storage/a.png', ImagenDeCorreo::url('http://localhost:8000/storage/a.png'));
    }

    // ───────── Cuándo no se muestra ─────────

    public function test_without_an_image_there_is_no_url(): void
    {
        $this->assertNull(ImagenDeCorreo::url(null));
        $this->assertNull(ImagenDeCorreo::url(''));
        $this->assertNull(ImagenDeCorreo::url('   '));
    }

    public function test_webp_avif_and_svg_are_not_shown(): void
    {
        foreach (['casco.webp', 'casco.AVIF', 'logo.svg', 'sin-extension'] as $archivo) {
            $this->assertNull(ImagenDeCorreo::url(self::API.'/storage/products/'.$archivo), $archivo);
        }

        // La extensión no distingue mayúsculas.
        $this->assertNotNull(ImagenDeCorreo::url(self::API.'/storage/products/casco.JPG'));
    }

    public function test_a_missing_file_in_our_storage_is_not_shown(): void
    {
        Storage::disk('public')->put('products/Casco Azul.png', 'png');

        $this->assertNotNull(ImagenDeCorreo::url(self::API.'/storage/products/Casco Azul.png', comprobarArchivo: true));
        $this->assertNotNull(ImagenDeCorreo::url(self::API.'/storage/products/Casco%20Azul.png', comprobarArchivo: true));
        $this->assertNull(ImagenDeCorreo::url(self::API.'/storage/products/Casco Borrado.png', comprobarArchivo: true));

        // Una URL de otro host (R2, un CDN) no se puede comprobar sin pedirla: se confía.
        $this->assertNotNull(ImagenDeCorreo::url('https://cdn.example.com/no-se-sabe.png', comprobarArchivo: true));
    }

    // ───────── En el correo ─────────

    private bool $sembrado = false;

    private function pedidoConImagen(?string $archivo): Order
    {
        if (! $this->sembrado) {
            $this->seed(DatabaseSeeder::class);
            Mail::fake();
            $this->sembrado = true;
        }

        $product = Product::factory()->create(['name' => 'Pantalón Converse', 'price' => 100, 'sale_price' => null, 'weight' => 1]);
        $product->images()->delete();
        if ($archivo !== null) {
            // Como lo guarda el panel: ruta relativa al disco public, con espacios.
            $product->images()->create(['url' => 'products/'.$archivo, 'is_primary' => true, 'sort_order' => 0]);
        }
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $id = $this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Carla Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ])->assertCreated()->json('order.id');

        return Order::findOrFail($id);
    }

    public function test_the_mail_thumbnail_uses_an_encoded_https_url(): void
    {
        Storage::disk('public')->put('products/6ac5143149ec1_Black Converse Pants on White Background.png', 'png');
        $order = $this->pedidoConImagen('6ac5143149ec1_Black Converse Pants on White Background.png');

        $html = (new PaymentRecoveryMail($order))->render();

        $this->assertStringContainsString(
            '<img src="'.self::API.'/storage/products/6ac5143149ec1_Black%20Converse%20Pants%20on%20White%20Background.png" width="64" height="64"',
            $html
        );
        // Es la única imagen del correo, y no sale por http.
        $this->assertSame(1, substr_count($html, '<img '));
        $this->assertStringNotContainsString('src="http://', $html);
    }

    public function test_the_mail_shows_no_broken_thumbnail_for_a_missing_file_or_webp(): void
    {
        foreach (['Casco Borrado.png', 'casco.webp', null] as $archivo) {
            $html = (new PaymentRecoveryMail($this->pedidoConImagen($archivo)))->render();

            $this->assertStringNotContainsString('width="64" height="64"', $html, (string) $archivo);
            // El producto sigue en el resumen, solo sin miniatura.
            $this->assertStringContainsString('Pantalón Converse', html_entity_decode($html, ENT_QUOTES));
        }
    }
}
