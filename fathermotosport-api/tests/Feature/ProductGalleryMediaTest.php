<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Unit\VideoInicioRapidoTest;

/**
 * Galería de productos con imágenes, GIF y videos cortos mezclados, en el orden que se
 * arrastra en el panel.
 *
 * Lo que importa: que las galerías de siempre se vean igual (mismo orden, misma
 * principal), que entren videos y GIF de hasta 50 MB, que la imagen de tarjetas y
 * carrito nunca sea un video, y que no queden archivos huérfanos en el disco.
 */
class ProductGalleryMediaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_15_100001_add_media_to_product_images_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    /** Un producto sin la imagen de relleno que le pone la factory. */
    private function producto(): Product
    {
        $producto = Product::factory()->create(['is_active' => true]);
        DB::table('product_images')->where('product_id', $producto->id)->delete();

        return $producto->fresh();
    }

    /** Un elemento de galería con su archivo ya en el disco, como lo dejaría el panel. */
    private function medio(Product $producto, string $url, array $extra = []): ProductImage
    {
        Storage::disk('public')->put($url, 'x');
        foreach (['poster_url', 'thumbnail_url'] as $columna) {
            if (! empty($extra[$columna])) {
                Storage::disk('public')->put($extra[$columna], 'x');
            }
        }

        return ProductImage::create(array_merge(['product_id' => $producto->id, 'url' => $url], $extra));
    }

    private function gifReal(): string
    {
        $imagen = imagecreatetruecolor(4, 4);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 0, 30));
        ob_start();
        imagegif($imagen);

        return ob_get_clean();
    }

    private function crearConGaleria(array $galeria)
    {
        $brand = Brand::firstOrCreate(['slug' => 'galeria'], ['name' => 'Galeria', 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => 'galeria-cat'], ['name' => 'Galería', 'is_active' => true, 'sort_order' => 1]);

        return Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Casco Galería',
                'slug' => 'casco-galeria',
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'sku' => 'GAL-001',
                'price' => 150,
                'description' => 'Casco de prueba',
                'images' => $galeria,
                'variants' => [
                    ['sku' => 'GAL-001-M', 'size' => 'M', 'stock' => 5, 'is_active' => true],
                ],
            ])
            ->call('create');
    }

    // ───────────────── Migración: las galerías de siempre no cambian ─────────────────

    public function test_the_migration_keeps_the_order_that_was_shown_and_marks_gifs(): void
    {
        $producto = $this->producto();
        $migracion = require database_path(self::MIGRACION);
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('product_images', 'media_type'));

        // Como se mostraba: la principal primero, después sort_order y, en empate, id.
        $filas = [
            ['id' => '00000000-0000-0000-0000-00000000000c', 'url' => 'products/c.png', 'sort_order' => 0, 'is_primary' => false],
            ['id' => '00000000-0000-0000-0000-00000000000a', 'url' => 'products/a.gif', 'sort_order' => 0, 'is_primary' => false],
            ['id' => '00000000-0000-0000-0000-00000000000b', 'url' => 'products/b.JPG', 'sort_order' => 5, 'is_primary' => true],
            ['id' => '00000000-0000-0000-0000-00000000000d', 'url' => 'products/d.png', 'sort_order' => 2, 'is_primary' => false],
        ];
        foreach ($filas as $fila) {
            DB::table('product_images')->insert($fila + ['product_id' => $producto->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $migracion->up();
        $orden = fn () => DB::table('product_images')->orderBy('sort_order')->get(['url', 'sort_order', 'media_type', 'is_primary', 'with_sound', 'poster_url']);
        $despues = $orden();

        $this->assertSame(['products/b.JPG', 'products/a.gif', 'products/c.png', 'products/d.png'], $despues->pluck('url')->all());
        $this->assertSame([0, 1, 2, 3], $despues->pluck('sort_order')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(['image', 'gif', 'image', 'image'], $despues->pluck('media_type')->all());
        $this->assertTrue((bool) $despues[0]->is_primary, 'la principal sigue siendo la principal');
        $this->assertFalse((bool) $despues[0]->with_sound);
        $this->assertNull($despues[0]->poster_url);

        // Volver a correrla no cambia nada.
        $migracion->up();
        $this->assertEquals($despues, $orden());

        // Y la API la devuelve en ese mismo orden, con la misma imagen principal.
        $json = $this->getJson("/api/v1/products/{$producto->slug}")->assertOk()->json('data');
        $this->assertSame(['b.JPG', 'a.gif', 'c.png', 'd.png'], array_map(fn ($i) => basename($i['url']), $json['images']));
        $this->assertStringEndsWith('products/b.JPG', $json['primary_image']);
    }

    public function test_the_migration_does_not_renumber_again_once_the_columns_exist(): void
    {
        $producto = $this->producto();
        $video = $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0]);
        $imagen = $this->medio($producto, 'products/i.png', ['sort_order' => 1, 'is_primary' => true]);

        (require database_path(self::MIGRACION))->up();

        $this->assertSame(0, $video->fresh()->sort_order, 'un video arrastrado al principio se queda ahí');
        $this->assertSame(1, $imagen->fresh()->sort_order);
    }

    // ───────────────── Panel ─────────────────

    public function test_images_gifs_and_videos_are_saved_in_the_dragged_order(): void
    {
        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->create('intro.mp4', 2048, 'video/mp4')], 'poster_url' => [UploadedFile::fake()->image('portada.jpg', 800, 800)], 'with_sound' => true],
            ['url' => [UploadedFile::fake()->image('frente.jpg', 600, 600)], 'is_primary' => true],
            ['url' => [UploadedFile::fake()->createWithContent('giro.gif', $this->gifReal())]],
            ['url' => [UploadedFile::fake()->create('detalle.mov', 1024, 'video/quicktime')]],
        ])->assertHasNoFormErrors();

        $galeria = Product::where('slug', 'casco-galeria')->firstOrFail()->images()->orderBy('sort_order')->get();

        $this->assertSame(['video', 'image', 'gif', 'video'], $galeria->pluck('media_type')->all());
        $this->assertSame([1, 2, 3, 4], $galeria->pluck('sort_order')->all());

        [$video, $imagen, $gif, $mov] = $galeria->all();
        $this->assertStringStartsWith('products/videos/', $video->url);
        $this->assertStringStartsWith('products/posters/', $video->poster_url);
        $this->assertTrue($video->with_sound);
        $this->assertFalse($video->is_primary);
        $this->assertTrue($imagen->is_primary);
        $this->assertStringStartsWith('products/', $imagen->url);
        $this->assertStringStartsWith('products/videos/', $mov->url);
        $this->assertFalse($mov->with_sound, 'el sonido está apagado por defecto');

        foreach ([$video->url, $video->poster_url, $imagen->url, $gif->url, $mov->url] as $ruta) {
            Storage::disk('public')->assertExists($ruta);
        }

        // El GIF guarda su primer fotograma como PNG, para miniaturas y tarjetas.
        $this->assertSame(ProductImage::rutaDeFotograma($gif->url), $gif->thumbnail_url);
        $this->assertStringStartsWith('products/fotogramas/', $gif->thumbnail_url);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('public')->get($gif->thumbnail_url));
    }

    public function test_any_video_format_is_accepted(): void
    {
        $galeria = [];
        foreach (['webm' => 'video/webm', 'm4v' => 'video/x-m4v', 'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'ogv' => 'video/ogg', 'wmv' => 'video/x-ms-wmv'] as $ext => $mime) {
            $galeria[] = ['url' => [UploadedFile::fake()->create("clip.{$ext}", 512, $mime)]];
        }

        $this->crearConGaleria($galeria)->assertHasNoFormErrors();

        $this->assertSame(array_fill(0, 6, 'video'), ProductImage::orderBy('sort_order')->pluck('media_type')->all());
    }

    public function test_a_video_or_gif_of_exactly_50_mb_is_accepted(): void
    {
        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->create('justo.mp4', ProductImage::MAX_VIDEO_GIF_KB, 'video/mp4')]],
            ['url' => [UploadedFile::fake()->create('justo.gif', ProductImage::MAX_VIDEO_GIF_KB, 'image/gif')]],
        ])->assertHasNoFormErrors();

        $this->assertSame(2, ProductImage::count());
        $this->assertSame(51200, ProductImage::MAX_VIDEO_GIF_KB);
    }

    public function test_a_video_or_gif_over_50_mb_is_rejected(): void
    {
        foreach (['grande.mp4' => 'video/mp4', 'grande.gif' => 'image/gif'] as $nombre => $mime) {
            $this->crearConGaleria([
                ['url' => [UploadedFile::fake()->create($nombre, ProductImage::MAX_VIDEO_GIF_KB + 1, $mime)]],
            ])->assertHasFormErrors(['images.0.url']);
        }

        $this->assertSame(0, Product::where('slug', 'casco-galeria')->count());
    }

    public function test_images_keep_having_no_size_limit_of_their_own(): void
    {
        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->create('enorme.jpg', ProductImage::MAX_VIDEO_GIF_KB + 1, 'image/jpeg')]],
        ])->assertHasNoFormErrors();

        $this->assertSame('image', ProductImage::firstOrFail()->media_type);
    }

    public function test_a_file_that_is_not_an_image_or_video_is_rejected(): void
    {
        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->create('ficha.pdf', 100, 'application/pdf')]],
        ])->assertHasFormErrors(['images.0.url']);

        $this->assertSame(0, ProductImage::count());
    }

    public function test_only_one_image_can_be_primary(): void
    {
        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->image('a.jpg', 600, 600)], 'is_primary' => true],
            ['url' => [UploadedFile::fake()->image('b.jpg', 600, 600)], 'is_primary' => true],
        ])->assertHasFormErrors(['images']);

        $this->assertSame(0, ProductImage::count());
    }

    public function test_the_form_has_no_numeric_order_field_and_explains_formats(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/i.png');

        Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()])
            ->assertSee('Imágenes y videos')
            ->assertSee('MP4 H.264 recomendado. Si no se reproduce, conviértelo a MP4 con HandBrake. Los GIF pesan mucho: mejor MP4.')
            ->assertDontSee('Marca solo una como principal.');

        $this->assertStringNotContainsString(
            "TextInput::make('sort_order')",
            file_get_contents(app_path('Filament/Resources/ProductResource.php'))
        );
    }

    public function test_an_mp4_with_its_index_at_the_end_is_reordered_when_saved(): void
    {
        [$bytes] = VideoInicioRapidoTest::mp4(moovAlFinal: true);

        $this->crearConGaleria([
            ['url' => [UploadedFile::fake()->createWithContent('celular.mp4', $bytes)]],
        ])->assertHasNoFormErrors();

        $guardado = Storage::disk('public')->get(ProductImage::firstOrFail()->url);
        $this->assertSame(strlen($bytes), strlen($guardado));
        $this->assertLessThan(strpos($guardado, 'mdat'), strpos($guardado, 'moov'));
    }

    // ───────────────── "Poner al principio" ─────────────────

    /** Producto con imagen marcada, GIF, video e imagen, en ese orden (sort_order 1..4). */
    private function galeriaParaReordenar(): Product
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/marcada.png', ['sort_order' => 1, 'is_primary' => true]);
        $this->medio($producto, 'products/g.gif', ['sort_order' => 2, 'thumbnail_url' => 'products/fotogramas/g.png']);
        $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 3, 'poster_url' => 'products/posters/v.jpg']);
        $this->medio($producto, 'products/otra.png', ['sort_order' => 4]);

        return $producto;
    }

    /** La clave de la fila del repeater cuyo archivo termina en $archivo. */
    private function filaDe($livewire, string $archivo): string
    {
        foreach ($livewire->get('data.images') as $clave => $fila) {
            $url = is_array($fila['url'] ?? null) ? reset($fila['url']) : ($fila['url'] ?? '');
            if (str_ends_with((string) $url, $archivo)) {
                return $clave;
            }
        }

        $this->fail("No hay fila con {$archivo}");
    }

    private function ordenGuardado(Product $producto): array
    {
        return $producto->images()->orderBy('sort_order')->get()
            ->map(fn (ProductImage $i) => [basename($i->url), $i->sort_order])->all();
    }

    public function test_put_first_moves_a_video_gif_or_image_to_the_top_and_renumbers(): void
    {
        $casos = [
            'v.mp4' => ['v.mp4', 'marcada.png', 'g.gif', 'otra.png'],
            'g.gif' => ['g.gif', 'marcada.png', 'v.mp4', 'otra.png'],
            'otra.png' => ['otra.png', 'marcada.png', 'g.gif', 'v.mp4'],
        ];

        foreach ($casos as $archivo => $esperado) {
            $producto = $this->galeriaParaReordenar();

            $livewire = Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()]);
            $livewire->callFormComponentAction('images', 'ponerAlPrincipio', arguments: ['item' => $this->filaDe($livewire, $archivo)])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame(
                array_map(fn ($nombre, $i) => [$nombre, $i + 1], $esperado, array_keys($esperado)),
                $this->ordenGuardado($producto),
                "{$archivo} al principio"
            );

            // "Principal" no cambia: la marcada sigue siendo la imagen de tarjetas y carrito.
            $producto->refresh();
            $this->assertSame(['marcada.png'], $producto->images()->where('is_primary', true)->get()->map(fn ($i) => basename($i->url))->all());
            $this->assertStringEndsWith('products/marcada.png', $producto->primary_image);
        }
    }

    public function test_put_first_on_the_first_row_is_disabled_and_changes_nothing(): void
    {
        $producto = $this->galeriaParaReordenar();
        $antes = $this->ordenGuardado($producto);

        $livewire = Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()]);
        $livewire
            ->assertSee('Poner al principio')
            ->assertFormComponentActionDisabled('images', 'ponerAlPrincipio', arguments: ['item' => $this->filaDe($livewire, 'marcada.png')])
            ->assertFormComponentActionEnabled('images', 'ponerAlPrincipio', arguments: ['item' => $this->filaDe($livewire, 'otra.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($antes, $this->ordenGuardado($producto));
    }

    public function test_put_first_keeps_the_arrows_and_the_limit_of_ten(): void
    {
        $producto = $this->galeriaParaReordenar();
        $livewire = Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()]);
        $fila = $this->filaDe($livewire, 'g.gif');

        $livewire
            ->assertFormComponentActionVisible('images', 'moveUp', arguments: ['item' => $fila])
            ->assertFormComponentActionVisible('images', 'moveDown', arguments: ['item' => $fila]);

        $repeater = $livewire->instance()->form->getComponent('data.images');
        $this->assertSame(10, $repeater->getMaxItems());
        $this->assertTrue($repeater->isReorderableWithButtons());
        $this->assertTrue($repeater->isReorderableWithDragAndDrop());
    }

    // ───────────────── Lo que no corresponde a cada tipo ─────────────────

    public function test_poster_sound_and_primary_only_stay_where_they_apply(): void
    {
        $producto = $this->producto();

        $imagen = $this->medio($producto, 'products/i.png', ['with_sound' => true, 'poster_url' => 'products/posters/p.jpg', 'is_primary' => true]);
        $gif = $this->medio($producto, 'products/g.gif', ['is_primary' => true]);

        $this->assertSame('image', $imagen->media_type);
        $this->assertFalse($imagen->with_sound);
        $this->assertNull($imagen->poster_url);
        $this->assertTrue($imagen->is_primary);
        $this->assertSame('gif', $gif->media_type);
        $this->assertFalse($gif->is_primary, 'solo una imagen puede ser principal');
    }

    // ───────────────── Imagen principal: nunca un video ─────────────────

    public function test_with_a_video_first_the_main_image_is_the_first_image(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0, 'poster_url' => 'products/posters/v.jpg']);
        $this->medio($producto, 'products/primera.png', ['sort_order' => 1]);
        $this->medio($producto, 'products/segunda.png', ['sort_order' => 2]);

        $this->assertStringEndsWith('products/primera.png', $producto->fresh()->primary_image);
    }

    public function test_the_marked_image_wins_wherever_it_is(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0]);
        $this->medio($producto, 'products/primera.png', ['sort_order' => 1]);
        $this->medio($producto, 'products/marcada.png', ['sort_order' => 2, 'is_primary' => true]);

        $this->assertStringEndsWith('products/marcada.png', $producto->fresh()->primary_image);
    }

    public function test_without_images_the_gif_frame_then_the_video_poster_then_nothing(): void
    {
        $producto = $this->producto();
        $video = $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0, 'poster_url' => 'products/posters/v.jpg']);
        $this->assertStringEndsWith('products/posters/v.jpg', $producto->fresh()->primary_image);

        Storage::disk('public')->put('products/g.gif', $this->gifReal());
        ProductImage::create(['product_id' => $producto->id, 'url' => 'products/g.gif', 'sort_order' => 1]);
        $this->assertStringEndsWith(ProductImage::rutaDeFotograma('products/g.gif'), $producto->fresh()->primary_image);

        ProductImage::where('media_type', 'gif')->get()->each->delete();
        $video->update(['poster_url' => null]);
        $this->assertNull($producto->fresh()->primary_image, 'un video nunca es la imagen principal');
    }

    public function test_a_gif_whose_frame_could_not_be_extracted_is_used_as_it_is(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/roto.gif');

        $medio = ProductImage::firstOrFail();
        $this->assertNull($medio->thumbnail_url);
        $this->assertStringEndsWith('products/roto.gif', $producto->fresh()->primary_image);
    }

    public function test_cart_order_and_upsell_never_receive_a_video(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0]);
        $this->medio($producto, 'products/foto.png', ['sort_order' => 1]);

        // Carrito, pedidos, upsell y correos leen todos product->primary_image.
        $variante = $producto->variants()->with('product.images')->firstOrFail();
        $this->assertStringEndsWith('products/foto.png', $variante->product->primary_image);

        $json = $this->getJson('/api/v1/products')->assertOk()->json('data');
        $this->assertStringEndsWith('products/foto.png', collect($json)->firstWhere('id', $producto->id)['primary_image']);
    }

    // ───────────────── API ─────────────────

    public function test_the_api_returns_type_poster_and_sound_in_the_dragged_order(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/foto.png', ['sort_order' => 2, 'is_primary' => true]);
        $this->medio($producto, 'products/videos/v.mp4', ['sort_order' => 0, 'poster_url' => 'products/posters/v.jpg', 'with_sound' => true]);
        $this->medio($producto, 'products/g.gif', ['sort_order' => 1]);

        $imagenes = $this->getJson("/api/v1/products/{$producto->slug}")->assertOk()->json('data.images');

        $this->assertSame(['video', 'gif', 'image'], array_column($imagenes, 'type'));
        $this->assertStringEndsWith('storage/products/videos/v.mp4', $imagenes[0]['url']);
        $this->assertStringStartsWith('http', $imagenes[0]['poster_url']);
        $this->assertStringEndsWith('storage/products/posters/v.jpg', $imagenes[0]['poster_url']);
        $this->assertTrue($imagenes[0]['with_sound']);
        $this->assertFalse($imagenes[2]['with_sound']);
        $this->assertNull($imagenes[2]['poster_url']);
    }

    // ───────────────── Archivos huérfanos ─────────────────

    public function test_removing_the_whole_gallery_in_the_panel_deletes_its_files(): void
    {
        $producto = $this->producto();
        $video = $this->medio($producto, 'products/videos/v.mp4', ['poster_url' => 'products/posters/v.jpg']);
        $imagen = $this->medio($producto, 'products/i.png', ['sort_order' => 1]);

        Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()])
            ->fillForm(['images' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $producto->images()->count());
        foreach (['products/videos/v.mp4', 'products/posters/v.jpg', 'products/i.png'] as $ruta) {
            Storage::disk('public')->assertMissing($ruta);
        }
    }

    public function test_replacing_the_file_or_the_poster_deletes_the_old_one(): void
    {
        $producto = $this->producto();
        $video = $this->medio($producto, 'products/videos/viejo.mp4', ['poster_url' => 'products/posters/viejo.jpg']);
        Storage::disk('public')->put('products/posters/nuevo.jpg', 'x');
        Storage::disk('public')->put('products/videos/nuevo.mp4', 'x');

        $video->update(['poster_url' => 'products/posters/nuevo.jpg']);
        Storage::disk('public')->assertMissing('products/posters/viejo.jpg');

        $video->update(['url' => 'products/videos/nuevo.mp4']);
        Storage::disk('public')->assertMissing('products/videos/viejo.mp4');
        Storage::disk('public')->assertExists('products/videos/nuevo.mp4');
        Storage::disk('public')->assertExists('products/posters/nuevo.jpg');
    }

    public function test_switching_a_video_to_an_image_deletes_its_poster(): void
    {
        $producto = $this->producto();
        $medio = $this->medio($producto, 'products/videos/v.mp4', ['poster_url' => 'products/posters/v.jpg', 'with_sound' => true]);
        Storage::disk('public')->put('products/foto.png', 'x');

        $medio->update(['url' => 'products/foto.png']);

        $this->assertSame('image', $medio->media_type);
        $this->assertFalse($medio->with_sound);
        Storage::disk('public')->assertMissing('products/posters/v.jpg');
        Storage::disk('public')->assertMissing('products/videos/v.mp4');
    }

    public function test_deleting_a_gif_deletes_its_frame_too(): void
    {
        $producto = $this->producto();
        Storage::disk('public')->put('products/g.gif', $this->gifReal());
        $gif = ProductImage::create(['product_id' => $producto->id, 'url' => 'products/g.gif']);
        Storage::disk('public')->assertExists(ProductImage::rutaDeFotograma('products/g.gif'));

        $gif->delete();

        Storage::disk('public')->assertMissing('products/g.gif');
        Storage::disk('public')->assertMissing(ProductImage::rutaDeFotograma('products/g.gif'));
    }

    public function test_a_file_used_by_another_item_is_not_deleted(): void
    {
        $uno = $this->producto();
        $otro = $this->producto();
        $a = $this->medio($uno, 'products/compartida.png');
        $this->medio($otro, 'products/compartida.png');

        $a->delete();

        Storage::disk('public')->assertExists('products/compartida.png');
    }

    public function test_the_trash_keeps_the_files_and_deleting_for_good_removes_them(): void
    {
        $producto = $this->producto();
        $this->medio($producto, 'products/videos/v.mp4', ['poster_url' => 'products/posters/v.jpg']);

        $producto->delete();
        Storage::disk('public')->assertExists('products/videos/v.mp4');
        $this->assertSame(1, ProductImage::count());

        $producto->forceDelete();
        Storage::disk('public')->assertMissing('products/videos/v.mp4');
        Storage::disk('public')->assertMissing('products/posters/v.jpg');
        $this->assertSame(0, ProductImage::count());
    }
}
