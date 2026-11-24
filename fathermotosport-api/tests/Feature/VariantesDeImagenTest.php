<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Imágenes livianas: /storage/v/{ancho}/{ruta}.webp genera una vez una versión WebP
 * achicada del archivo del storage público, y nunca sale de ese storage.
 */
class VariantesDeImagenTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = 'pruebas-variantes-'.uniqid();
        File::ensureDirectoryExists(storage_path('app/public/'.$this->carpeta));

        $img = imagecreatetruecolor(2000, 1000);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        imagejpeg($img, storage_path("app/public/{$this->carpeta}/casco.jpg"), 95);
        imagepng($img, storage_path("app/public/{$this->carpeta}/banner.png"));
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/'.$this->carpeta));
        foreach ([320, 640, 960, 1280, 1920] as $ancho) {
            File::deleteDirectory(storage_path("app/public/v/{$ancho}/{$this->carpeta}"));
        }
        parent::tearDown();
    }

    public function test_it_generates_a_smaller_webp_at_the_requested_width(): void
    {
        $respuesta = $this->get("/storage/v/640/{$this->carpeta}/casco.jpg.webp")->assertOk();

        $this->assertSame('image/webp', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $respuesta->headers->get('Cache-Control'));
        $this->assertNull($respuesta->headers->get('Set-Cookie'), 'Sin cookies: se tiene que poder cachear.');

        $archivo = storage_path("app/public/v/640/{$this->carpeta}/casco.jpg.webp");
        $this->assertFileExists($archivo, 'Queda como archivo estático para Apache.');
        [$ancho, $alto] = getimagesize($archivo);
        $this->assertSame([640, 320], [$ancho, $alto]);
        $this->assertLessThan(filesize(storage_path("app/public/{$this->carpeta}/casco.jpg")), filesize($archivo));
    }

    public function test_png_banners_are_converted_too(): void
    {
        $this->get("/storage/v/1280/{$this->carpeta}/banner.png.webp")->assertOk();
        $this->assertFileExists(storage_path("app/public/v/1280/{$this->carpeta}/banner.png.webp"));
    }

    public function test_it_never_upscales(): void
    {
        $this->get("/storage/v/1920/{$this->carpeta}/casco.jpg.webp")->assertOk();
        $this->assertSame(1920, getimagesize(storage_path("app/public/v/1920/{$this->carpeta}/casco.jpg.webp"))[0]);

        $chica = storage_path("app/public/{$this->carpeta}/chica.jpg");
        $img = imagecreatetruecolor(300, 200);
        imagejpeg($img, $chica);
        $this->get("/storage/v/640/{$this->carpeta}/chica.jpg.webp")->assertOk();
        $this->assertSame(300, getimagesize(storage_path("app/public/v/640/{$this->carpeta}/chica.jpg.webp"))[0]);
    }

    public function test_only_allowed_widths(): void
    {
        $this->get("/storage/v/641/{$this->carpeta}/casco.jpg.webp")->assertNotFound();
        $this->get("/storage/v/99999/{$this->carpeta}/casco.jpg.webp")->assertNotFound();
    }

    public function test_it_cannot_escape_the_public_storage(): void
    {
        $this->get('/storage/v/640/../../.env.webp')->assertNotFound();
        $this->get('/storage/v/640/..%2F..%2F.env.webp')->assertNotFound();
        $this->get("/storage/v/640/{$this->carpeta}/../../../.env.webp")->assertNotFound();
    }

    public function test_missing_or_non_image_files_are_404(): void
    {
        $this->get("/storage/v/640/{$this->carpeta}/no-existe.jpg.webp")->assertNotFound();
        file_put_contents(storage_path("app/public/{$this->carpeta}/nota.txt"), 'hola');
        $this->get("/storage/v/640/{$this->carpeta}/nota.txt.webp")->assertNotFound();
        $this->get("/storage/v/640/{$this->carpeta}/casco.jpg")->assertNotFound();
    }
}
