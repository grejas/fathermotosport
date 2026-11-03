<?php

namespace Tests\Feature;

use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Support\FotoDeResena;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fotos en las reseñas: hasta 3 por reseña, re-codificadas con GD (sin EXIF ni nada
 * escondido), pendientes en el disco privado hasta que el admin las aprueba. Solo se
 * publican las fotos aprobadas de reseñas aprobadas (is_approved).
 */
class ReviewPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_22_100001_create_review_images_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    private function producto(): Product
    {
        return Product::factory()->create(['is_active' => true]);
    }

    /** JPEG real hecho con GD, con un segmento EXIF (orientación y un "GPS") y basura pegada al final. */
    private function jpegConExif(int $ancho = 40, int $alto = 20, int $orientacion = 1): UploadedFile
    {
        $gd = imagecreatetruecolor($ancho, $alto);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 200, 30, 30));
        ob_start();
        imagejpeg($gd, null, 90);
        $jpeg = (string) ob_get_clean();

        // TIFF little-endian con un IFD de una entrada: Orientation (0x0112), SHORT, 1 valor.
        $tiff = 'II*'."\0".pack('V', 8).pack('v', 1).pack('vvV', 0x0112, 3, 1).pack('vv', $orientacion, 0).pack('V', 0)
            .'GPS-SECRETO-LAT-16.5';
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;
        $contenido = substr($jpeg, 0, 2).$app1.substr($jpeg, 2).'<?php echo "oculto"; ?>';

        $ruta = tempnam(sys_get_temp_dir(), 'resena').'.jpg';
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, 'IMG_0001.jpg', 'image/jpeg', null, true);
    }

    private function png(int $ancho = 30, int $alto = 30): UploadedFile
    {
        $gd = imagecreatetruecolor($ancho, $alto);
        ob_start();
        imagepng($gd);
        $ruta = tempnam(sys_get_temp_dir(), 'resena').'.png';
        file_put_contents($ruta, (string) ob_get_clean());

        return new UploadedFile($ruta, 'captura.png', 'image/png', null, true);
    }

    private function enviar(Product $producto, array $fotos, ?User $cliente = null)
    {
        return $this->actingAs($cliente ?? User::factory()->create())
            ->post("/api/v1/products/{$producto->slug}/reviews", [
                'rating' => 5,
                'title' => 'Muy bueno',
                'comment' => 'Llegó perfecto.',
                'photos' => $fotos,
            ], ['Accept' => 'application/json']);
    }

    /** Reseña aprobada con una foto ya subida (pendiente). */
    private function resenaConFoto(bool $aprobada = true): Review
    {
        $this->enviar($this->producto(), [$this->png()])->assertCreated();
        $review = Review::latest()->firstOrFail();
        $review->update(['is_approved' => $aprobada]);

        return $review;
    }

    public function test_a_review_with_three_photos_is_created_and_the_photos_stay_pending_in_private_disk(): void
    {
        $producto = $this->producto();

        $this->enviar($producto, [$this->png(), $this->jpegConExif(), $this->png(50, 20)])
            ->assertCreated()
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonMissingPath('data.images');

        $fotos = ReviewImage::orderBy('sort_order')->get();
        $this->assertCount(3, $fotos);
        $this->assertSame([0, 1, 2], $fotos->pluck('sort_order')->all());

        foreach ($fotos as $foto) {
            $this->assertSame(ReviewImage::PENDIENTE, $foto->status);
            $this->assertStringStartsWith(FotoDeResena::CARPETA_PENDIENTE.'/', $foto->path);
            Storage::disk('local')->assertExists([$foto->path, $foto->thumb_path]);
            Storage::disk('public')->assertMissing([$foto->path, $foto->thumb_path]);
            // Nombre generado por el servidor, nunca el del cliente.
            $this->assertMatchesRegularExpression('#^resenas/pendientes/[0-9a-f-]{36}\.(webp|jpg)$#', $foto->path);
        }
        $this->assertStringNotContainsString('IMG_0001', $fotos->pluck('path')->implode(' '));
    }

    public function test_exif_and_hidden_content_are_removed_and_orientation_is_applied(): void
    {
        $this->enviar($this->producto(), [$this->jpegConExif(40, 20, 6)])->assertCreated();

        $foto = ReviewImage::firstOrFail();
        $bytes = Storage::disk('local')->get($foto->path);

        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('GPS-SECRETO', $bytes);
        $this->assertStringNotContainsString('<?php', $bytes);
        $this->assertStringNotContainsString('<?php', Storage::disk('local')->get($foto->thumb_path));

        $info = getimagesizefromstring($bytes);
        $this->assertSame(FotoDeResena::soportaWebp() ? 'image/webp' : 'image/jpeg', $info['mime']);
        // Orientación 6 (girada 90°): la foto de 40x20 queda de 20x40.
        $this->assertSame([20, 40], [$info[0], $info[1]]);
        $this->assertSame([20, 40], [$foto->width, $foto->height]);
    }

    public function test_large_photos_are_reduced_to_1600_and_get_a_400_thumbnail(): void
    {
        $this->enviar($this->producto(), [$this->png(2000, 1000)])->assertCreated();

        $foto = ReviewImage::firstOrFail();
        $this->assertSame([1600, 800], [$foto->width, $foto->height]);
        $miniatura = getimagesizefromstring(Storage::disk('local')->get($foto->thumb_path));
        $this->assertSame([400, 200], [$miniatura[0], $miniatura[1]]);
    }

    public function test_a_fake_image_is_rejected_by_its_real_type(): void
    {
        $falsa = UploadedFile::fake()->createWithContent('foto.jpg', '<?php system($_GET["c"]); ?>');
        $gifReal = tempnam(sys_get_temp_dir(), 'resena');
        $gd = imagecreatetruecolor(10, 10);
        imagegif($gd, $gifReal);
        $gifComoJpg = new UploadedFile($gifReal, 'foto.jpg', 'image/jpeg', null, true);

        $this->enviar($this->producto(), [$falsa])->assertStatus(422)->assertJsonValidationErrors('photos.0');
        $this->enviar($this->producto(), [$gifComoJpg])->assertStatus(422)->assertJsonValidationErrors('photos.0');

        $this->assertSame(0, Review::count());
        $this->assertSame(0, ReviewImage::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_a_photo_over_5_mb_is_rejected(): void
    {
        $grande = UploadedFile::fake()->create('grande.jpg', FotoDeResena::MAX_KB + 1, 'image/jpeg');

        $this->enviar($this->producto(), [$grande])->assertStatus(422)->assertJsonValidationErrors('photos.0');
        $this->assertSame(0, Review::count());
    }

    public function test_more_than_three_photos_are_rejected(): void
    {
        $this->enviar($this->producto(), [$this->png(), $this->png(), $this->png(), $this->png()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        $this->assertSame(0, Review::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_an_image_with_huge_dimensions_is_rejected_before_decoding(): void
    {
        // Cabecera PNG que declara 10000x10000 (100 MP) sin los datos: no se llega a decodificar.
        $ihdr = pack('NN', 10000, 10000)."\x08\x02\x00\x00\x00";
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
        $bomba = UploadedFile::fake()->createWithContent('bomba.png', $png);

        $this->enviar($this->producto(), [$bomba])->assertStatus(422)->assertJsonValidationErrors('photos.0');
    }

    public function test_a_review_without_photos_still_works(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/products/{$this->producto()->slug}/reviews", ['rating' => 4, 'comment' => 'Bien'])
            ->assertCreated();

        $this->assertSame(0, ReviewImage::count());
    }

    public function test_pending_photos_are_not_public_until_approved(): void
    {
        $review = $this->resenaConFoto();
        $foto = $review->images()->firstOrFail();

        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonPath('data.0.images', []);
        $this->getJson("/api/v1/products/{$review->product->slug}")->assertOk()->assertJsonPath('data.reviews.0.images', []);

        $foto->aprobar();
        $foto->refresh();

        $this->assertSame(ReviewImage::APROBADA, $foto->status);
        $this->assertNotNull($foto->approved_at);
        $this->assertStringStartsWith(FotoDeResena::CARPETA_PUBLICA.'/', $foto->path);
        Storage::disk('public')->assertExists([$foto->path, $foto->thumb_path]);
        $this->assertEmpty(Storage::disk('local')->allFiles());

        $this->getJson('/api/v1/reviews')->assertOk()
            ->assertJsonPath('data.0.images.0.id', $foto->id)
            ->assertJsonPath('data.0.images.0.url', fn (string $url) => str_ends_with($url, '/storage/'.$foto->path))
            ->assertJsonPath('data.0.images.0.thumb_url', fn (string $url) => str_ends_with($url, '/storage/'.$foto->thumb_path));
        $this->getJson("/api/v1/products/{$review->product->slug}")->assertOk()
            ->assertJsonPath('data.reviews.0.images.0.id', $foto->id);
    }

    public function test_a_review_that_is_not_approved_is_hidden_with_its_approved_photos(): void
    {
        $review = $this->resenaConFoto(aprobada: false);
        $review->images()->firstOrFail()->aprobar();

        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/products/{$review->product->slug}")->assertOk()->assertJsonCount(0, 'data.reviews');

        $review->update(['is_approved' => true]);
        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonCount(1, 'data.0.images');
    }

    public function test_the_public_api_has_no_verified_purchase_label(): void
    {
        $this->resenaConFoto();

        $json = $this->getJson('/api/v1/reviews')->assertOk()->getContent();
        $this->assertStringNotContainsStringIgnoringCase('verified', $json);
        $this->assertStringNotContainsStringIgnoringCase('verificad', $json);
    }

    public function test_deleting_a_photo_removes_its_files(): void
    {
        $review = $this->resenaConFoto();
        $pendiente = $review->images()->firstOrFail();
        $rutas = [$pendiente->path, $pendiente->thumb_path];

        $pendiente->delete();
        Storage::disk('local')->assertMissing($rutas);

        $this->enviar($this->producto(), [$this->png()])->assertCreated();
        $aprobada = ReviewImage::firstOrFail();
        $aprobada->aprobar();
        $aprobada->refresh()->delete();
        Storage::disk('public')->assertMissing([$aprobada->path, $aprobada->thumb_path]);
    }

    public function test_deleting_a_review_product_or_user_removes_the_photo_files(): void
    {
        // La reseña.
        $review = $this->resenaConFoto();
        $review->images()->firstOrFail()->aprobar();
        $review->delete();
        $this->assertSame(0, ReviewImage::count());
        $this->assertEmpty(Storage::disk('public')->allFiles('resenas'));

        // El producto borrado del todo (con la papelera no se borra nada).
        $review = $this->resenaConFoto();
        $review->product->delete();
        $this->assertSame(1, ReviewImage::count());
        $review->product()->withTrashed()->firstOrFail()->forceDelete();
        $this->assertSame(0, ReviewImage::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());

        // El cliente borrado del todo.
        $cliente = User::factory()->create();
        $this->enviar($this->producto(), [$this->png()], $cliente)->assertCreated();
        $cliente->forceDelete();
        $this->assertSame(0, ReviewImage::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_review_submissions_are_rate_limited(): void
    {
        $cliente = User::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->enviar($this->producto(), [], $cliente)->assertCreated();
        }
        $this->enviar($this->producto(), [], $cliente)->assertStatus(429);
    }

    public function test_the_product_listing_counts_only_approved_reviews(): void
    {
        $producto = $this->producto();
        Review::factory()->create(['product_id' => $producto->id, 'is_approved' => true]);
        Review::factory()->create(['product_id' => $producto->id, 'is_approved' => false, 'user_id' => User::factory()->create()->id]);

        $listado = collect($this->getJson('/api/v1/products?per_page=60')->assertOk()->json('data'));

        $this->assertSame(1, $listado->firstWhere('id', $producto->id)['reviews_count']);
    }

    public function test_private_photos_are_served_only_to_staff(): void
    {
        $foto = $this->resenaConFoto()->images()->firstOrFail();
        $url = route('panel.resenas.foto', [$foto, 'miniatura'], false);

        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->actingAs($this->admin())->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_panel_approves_and_deletes_photos_and_retires_reviews(): void
    {
        $review = $this->resenaConFoto(aprobada: false);
        $this->enviar($this->producto(), [$this->png(), $this->png()])->assertCreated();
        $otra = Review::where('id', '!=', $review->id)->firstOrFail();
        $foto = $review->images()->firstOrFail();

        $this->actingAs($this->admin());
        Livewire::test(ListReviews::class)
            ->assertTableActionExists('ver')
            ->assertTableActionExists('retirar')
            ->assertTableActionDoesNotExist('reject')
            ->callTableAction('approve', $review)
            ->call('aprobarFoto', $foto->id)
            ->call('eliminarFoto', $otra->images()->firstOrFail()->id)
            ->call('aprobarTodasLasFotos', $otra->id)
            ->callTableAction('retirar', $review->fresh())
            ->mountTableAction('ver', $otra)
            ->assertSee('Aprobada')
            ->assertDontSee('Compra verificada');

        $this->assertFalse($review->fresh()->is_approved);
        $this->assertSame(ReviewImage::APROBADA, $foto->fresh()->status);
        $this->assertSame(1, $otra->images()->count());
        $this->assertSame(ReviewImage::APROBADA, $otra->images()->firstOrFail()->status);
        $this->assertCount(4, Storage::disk('public')->allFiles('resenas'));
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_a_customer_cannot_call_the_panel_photo_actions(): void
    {
        $foto = $this->resenaConFoto(aprobada: false)->images()->firstOrFail();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/reviews')->assertForbidden();
        $this->assertSame(ReviewImage::PENDIENTE, $foto->fresh()->status);
    }

    public function test_the_migration_is_additive_and_can_run_again(): void
    {
        $this->resenaConFoto();
        $migracion = require base_path(self::MIGRACION);

        $migracion->up();
        $this->assertSame(1, ReviewImage::count());

        $migracion->down();
        $this->assertFalse(Schema::hasTable('review_images'));
        $this->assertSame(1, Review::count());

        $migracion->up();
        $this->assertTrue(Schema::hasTable('review_images'));
    }
}
