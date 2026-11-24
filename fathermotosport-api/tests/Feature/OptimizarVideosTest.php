<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * media:optimizar-videos: comprime los videos pesados con el mismo nombre, guarda el
 * original fuera de lo público, genera póster si falta y no repite lo ya hecho.
 */
class OptimizarVideosTest extends TestCase
{
    use RefreshDatabase;

    private string $ffmpeg = '/usr/local/bin/ffmpeg';

    private string $relativo;

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_executable($this->ffmpeg)) {
            $this->markTestSkipped('Sin ffmpeg en este entorno.');
        }
        $this->seed(DatabaseSeeder::class);
        config(['services.ffmpeg.path' => $this->ffmpeg]);

        $this->relativo = 'products/videos/prueba-'.uniqid().'.mp4';
        $destino = storage_path('app/public/'.$this->relativo);
        File::ensureDirectoryExists(dirname($destino));

        // 6 s de ruido 1080x1920 a bitrate alto: un "video de WhatsApp" pesado.
        (new Process([$this->ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=1080x1920:rate=30',
            '-f', 'lavfi', '-i', 'sine=frequency=440', '-t', '6', '-c:v', 'libx264', '-b:v', '8M', '-c:a', 'aac', $destino]))->mustRun();
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/public/'.$this->relativo));
        File::deleteDirectory(storage_path('app/public/products/videos/posters'));
        File::delete(storage_path('app/originales/'.$this->relativo));
        File::delete(storage_path('app/videos-optimizados.json'));
        parent::tearDown();
    }

    public function test_it_compresses_keeps_the_name_backs_up_and_adds_a_poster(): void
    {
        $archivo = storage_path('app/public/'.$this->relativo);
        $antes = filesize($archivo);
        $this->assertGreaterThan(3 * 1024 * 1024, $antes);

        $imagen = ProductImage::create([
            'product_id' => Product::factory()->create()->id,
            'url' => $this->relativo,
            'media_type' => ProductImage::VIDEO,
            'sort_order' => 0,
            'is_primary' => false,
        ]);

        $this->artisan('media:optimizar-videos', ['--limite' => 10])->assertSuccessful();

        clearstatcache();
        $this->assertLessThan($antes * 0.8, filesize($archivo), 'El video quedó más liviano con el mismo nombre.');
        $this->assertFileExists(storage_path('app/originales/'.$this->relativo), 'El original queda respaldado.');

        // Lado corto a 720 px.
        $info = (new Process([dirname($this->ffmpeg).'/ffprobe', '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height', '-of', 'csv=p=0', $archivo]));
        if (is_executable(dirname($this->ffmpeg).'/ffprobe')) {
            $info->mustRun();
            $this->assertSame('720,1280', trim($info->getOutput()));
        }

        $poster = $imagen->fresh()->poster_url;
        $this->assertNotNull($poster);
        $this->assertFileExists(storage_path('app/public/'.$poster));

        // Segunda corrida: ya está en el registro, no se toca.
        $mtime = filemtime($archivo);
        $this->artisan('media:optimizar-videos', ['--limite' => 10])->expectsOutputToContain('Listo: 0')->assertSuccessful();
        clearstatcache();
        $this->assertSame($mtime, filemtime($archivo));
    }

    public function test_without_ffmpeg_it_does_nothing(): void
    {
        config(['services.ffmpeg.path' => '/no/existe/ffmpeg']);
        $antes = filesize(storage_path('app/public/'.$this->relativo));

        $this->artisan('media:optimizar-videos')->assertSuccessful();

        $this->assertSame($antes, filesize(storage_path('app/public/'.$this->relativo)));
    }
}
