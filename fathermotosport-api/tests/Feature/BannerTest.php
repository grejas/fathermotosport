<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Models\Banner;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Unit\VideoInicioRapidoTest;

/**
 * Banners con imagen o video subido desde el panel.
 *
 * Lo que importa: que los banners de imagen de siempre no cambien, que entre cualquier
 * video (con o sin poster) dentro de los límites (tipo real y 150 MB), que la API dé URLs
 * listas para un <video>, y que no queden archivos huérfanos en el disco.
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        // Producción: la API y su /storage en https, y nombres de archivo con espacios.
        config([
            'app.url' => 'http://api.fathermotosport.com',
            'filesystems.disks.public.url' => 'http://api.fathermotosport.com/storage',
        ]);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function video(string $nombre = 'promo.mp4', int $kb = 2048, string $mime = 'video/mp4'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, $kb, $mime);
    }

    private function poster(string $nombre = 'portada.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 1280, 720);
    }

    private function crearVideo(array $datos = [])
    {
        return Livewire::test(CreateBanner::class)
            ->fillForm(array_merge([
                'title' => 'Nueva colección',
                'subtitle' => 'Cascos 2026',
                'media_type' => 'video',
                'video_path' => [$this->video()],
                'poster_path' => [$this->poster()],
                'position' => 'hero',
                'is_active' => true,
                'sort_order' => 0,
            ], $datos))
            ->call('create');
    }

    /** Un banner con sus archivos ya en el disco, como lo dejaría el panel. */
    private function bannerDeVideo(array $extra = []): Banner
    {
        foreach (['banners/videos/a.mp4', 'banners/videos/a-movil.mp4', 'banners/posters/a.jpg'] as $ruta) {
            Storage::disk('public')->put($ruta, 'x');
        }

        return Banner::create(array_merge([
            'title' => 'Video',
            'media_type' => 'video',
            'video_path' => 'banners/videos/a.mp4',
            'video_mobile_path' => 'banners/videos/a-movil.mp4',
            'poster_path' => 'banners/posters/a.jpg',
            'position' => 'hero',
            'is_active' => true,
        ], $extra));
    }

    // ───────────────── Banners de imagen de siempre ─────────────────

    public function test_an_existing_image_banner_is_returned_as_before(): void
    {
        $banner = Banner::create([
            'title' => 'Casco AGV',
            'image_url' => 'banners/casco.png',
            'link_url' => '/catalog',
            'button_text' => 'Ver',
            'position' => 'hero',
            'is_active' => true,
        ]);

        $this->assertSame('image', $banner->fresh()->media_type);

        $respuesta = $this->getJson('/api/v1/banners/hero')->assertOk();

        // La URL de la imagen sale del mismo helper de siempre (sin cambios).
        $this->assertSame(asset('storage/banners/casco.png'), $respuesta->json('data.0.image_url'));
        $respuesta
            ->assertJsonPath('data.0.title', 'Casco AGV')
            ->assertJsonPath('data.0.button_text', 'Ver')
            ->assertJsonPath('data.0.link_url', '/catalog')
            ->assertJsonPath('data.0.media_type', 'image')
            ->assertJsonPath('data.0.video_url', null)
            ->assertJsonPath('data.0.poster_url', null)
            ->assertJsonPath('data.0.video_with_sound', false);
    }

    public function test_an_image_banner_can_still_be_created_from_the_panel(): void
    {
        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title' => 'Imagen',
                'media_type' => 'image',
                'image_url' => [UploadedFile::fake()->image('casco.png', 800, 800)],
                'position' => 'footer',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::firstOrFail();
        $this->assertSame('image', $banner->media_type);
        $this->assertStringStartsWith('banners/', $banner->image_url);
        $this->assertNull($banner->video_path);
        Storage::disk('public')->assertExists($banner->image_url);
    }

    // ───────────────── Banners de video desde el panel ─────────────────

    public function test_a_video_banner_is_created_without_a_mobile_version(): void
    {
        $this->crearVideo()->assertHasNoFormErrors();

        $banner = Banner::firstOrFail();
        $this->assertSame('video', $banner->media_type);
        $this->assertStringStartsWith('banners/videos/', $banner->video_path);
        $this->assertStringStartsWith('banners/posters/', $banner->poster_path);
        $this->assertNull($banner->video_mobile_path);
        $this->assertNull($banner->image_url);
        $this->assertFalse($banner->video_with_sound);
        Storage::disk('public')->assertExists([$banner->video_path, $banner->poster_path]);
    }

    public function test_a_video_banner_is_created_with_a_mobile_version_and_sound(): void
    {
        $this->crearVideo([
            'video_path' => [$this->video('escritorio.webm', 4096, 'video/webm')],
            'video_mobile_path' => [$this->video('movil.mp4', 1024)],
            'video_with_sound' => true,
        ])->assertHasNoFormErrors();

        $banner = Banner::firstOrFail();
        $this->assertStringStartsWith('banners/videos/', $banner->video_mobile_path);
        $this->assertTrue($banner->video_with_sound);
        Storage::disk('public')->assertExists($banner->video_mobile_path);
    }

    public function test_a_video_of_exactly_150_mb_is_accepted(): void
    {
        $this->crearVideo(['video_path' => [$this->video('largo.mp4', 153600)]])->assertHasNoFormErrors();
    }

    /**
     * Cualquier video entra, en el principal y en el móvil. Los MIME son los que da el
     * servidor al leer el contenido de cada formato (finfo), no los de la extensión.
     */
    public function test_any_video_format_is_accepted(): void
    {
        $formatos = [
            'promo.mp4' => 'video/mp4',
            'promo.webm' => 'video/webm',
            'promo.mov' => 'video/quicktime',
            'promo.m4v' => 'video/x-m4v',
            'promo.avi' => 'video/x-msvideo',
            'promo.mkv' => 'video/x-matroska',
            'promo.ogv' => 'video/ogg',
            'promo.wmv' => 'video/x-ms-wmv',
            'otro.mp4' => 'application/mp4',
        ];

        foreach ($formatos as $nombre => $mime) {
            $this->crearVideo([
                'title' => $nombre,
                'video_path' => [$this->video($nombre, 1024, $mime)],
                'video_mobile_path' => [$this->video("movil-{$nombre}", 512, $mime)],
            ])->assertHasNoFormErrors();
        }

        $this->assertSame(count($formatos), Banner::where('media_type', 'video')->count());
    }

    public function test_a_file_that_is_not_a_video_is_rejected(): void
    {
        $noVideos = [
            'virus.mp4' => 'application/x-msdownload',
            'cancion.mp3' => 'audio/mpeg',
            'foto.png' => 'image/png',
            'catalogo.pdf' => 'application/pdf',
        ];

        foreach ($noVideos as $nombre => $mime) {
            $this->crearVideo(['video_path' => [$this->video($nombre, 10, $mime)]])
                ->assertHasFormErrors(['video_path']);
            $this->crearVideo(['video_mobile_path' => [$this->video("movil-{$nombre}", 10, $mime)]])
                ->assertHasFormErrors(['video_mobile_path']);
        }

        $this->assertSame(0, Banner::count());
    }

    /**
     * Primera barrera: la subida temporal de Livewire corta en 150 MB (el navegador
     * ve el error antes de terminar de subir).
     */
    public function test_the_livewire_upload_limit_is_150_mb(): void
    {
        $this->assertContains('max:153600', config('livewire.temporary_file_upload.rules'));
    }

    /**
     * Segunda barrera: aunque la subida temporal aceptara más (otra configuración o
     * una petición armada a mano), el formulario rechaza el archivo al guardar.
     */
    public function test_a_video_over_150_mb_is_rejected_when_saving(): void
    {
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:307200']]);

        $this->crearVideo(['video_path' => [$this->video('enorme.mp4', 153601)]])
            ->assertHasFormErrors(['video_path']);
        $this->crearVideo(['video_mobile_path' => [$this->video('enorme-movil.mp4', 153601)]])
            ->assertHasFormErrors(['video_mobile_path']);

        $this->assertSame(0, Banner::count());
    }

    public function test_a_video_banner_can_be_saved_without_poster(): void
    {
        $this->crearVideo(['poster_path' => []])->assertHasNoFormErrors();

        $banner = Banner::firstOrFail();
        $this->assertSame('video', $banner->media_type);
        $this->assertNull($banner->poster_path);
        $this->assertNull($banner->image_url);

        // La API lo devuelve con poster_url e image_url en null, sin romperse.
        $this->getJson('/api/v1/banners/hero')
            ->assertOk()
            ->assertJsonPath('data.0.media_type', 'video')
            ->assertJsonPath('data.0.poster_url', null)
            ->assertJsonPath('data.0.image_url', null)
            ->assertJsonPath('data.0.video_url', 'https://api.fathermotosport.com/storage/'.$banner->video_path);

        // Y el listado del panel también (su miniatura cae a vacío).
        Livewire::test(ListBanners::class)->assertOk()->assertCanSeeTableRecords([$banner]);
    }

    public function test_the_video_is_required_for_a_video_banner(): void
    {
        $this->crearVideo(['video_path' => []])->assertHasFormErrors(['video_path' => 'required']);
    }

    public function test_title_and_text_are_optional(): void
    {
        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title' => null,
                'subtitle' => null,
                'media_type' => 'image',
                'image_url' => [UploadedFile::fake()->image('casco.png', 800, 800)],
                'position' => 'hero',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->getJson('/api/v1/banners/hero')
            ->assertOk()
            ->assertJsonPath('data.0.title', null)
            ->assertJsonPath('data.0.subtitle', null);
    }

    public function test_the_form_explains_that_the_hero_keeps_its_own_texts(): void
    {
        Livewire::test(CreateBanner::class)
            ->assertSee("El hero conserva sus textos; el título y el texto solo se usan en 'Debajo de categorías'");
    }

    // ───────────────── Inicio rápido del video ─────────────────

    public function test_an_mp4_with_its_index_at_the_end_is_reordered_when_saved_from_the_panel(): void
    {
        [$bytes] = VideoInicioRapidoTest::mp4(moovAlFinal: true);

        $this->crearVideo(['video_path' => [UploadedFile::fake()->createWithContent('celular.mp4', $bytes)]])
            ->assertHasNoFormErrors();

        $guardado = Storage::disk('public')->get(Banner::firstOrFail()->video_path);
        $this->assertSame(strlen($bytes), strlen($guardado));
        $this->assertSame('moov', substr($guardado, strpos($guardado, 'ftyp') + 28 + 4, 4), 'el moov tiene que quedar justo después del ftyp');
        $this->assertGreaterThan(strpos($guardado, 'moov'), strpos($guardado, 'mdat'));
    }

    public function test_the_command_reorders_videos_uploaded_before(): void
    {
        [$bytes] = VideoInicioRapidoTest::mp4(moovAlFinal: true);
        Storage::disk('public')->put('banners/videos/viejo.mp4', $bytes);
        // Guardado sin pasar por el modelo, como un video subido antes de este cambio.
        Banner::withoutEvents(fn () => Banner::create([
            'title' => 'Viejo', 'media_type' => 'video', 'video_path' => 'banners/videos/viejo.mp4', 'position' => 'hero',
        ]));

        $this->artisan('banners:preparar-videos')
            ->expectsOutputToContain('banners/videos/viejo.mp4: tiene el índice al final')
            ->assertSuccessful();
        $this->assertSame($bytes, Storage::disk('public')->get('banners/videos/viejo.mp4'), 'sin --apply no se toca');

        $this->artisan('banners:preparar-videos --apply')
            ->expectsOutputToContain('banners/videos/viejo.mp4: reordenado')
            ->assertSuccessful();
        $reordenado = Storage::disk('public')->get('banners/videos/viejo.mp4');
        $this->assertLessThan(strpos($reordenado, 'mdat'), strpos($reordenado, 'moov'));
    }

    // ───────────────── API ─────────────────

    public function test_the_api_returns_absolute_https_encoded_video_urls(): void
    {
        foreach (['banners/videos/Promo Cascos.mp4', 'banners/posters/Portada 1.jpg'] as $ruta) {
            Storage::disk('public')->put($ruta, 'x');
        }
        Banner::create([
            'title' => 'Video',
            'subtitle' => 'Texto del banner',
            'media_type' => 'video',
            'video_path' => 'banners/videos/Promo Cascos.mp4',
            'poster_path' => 'banners/posters/Portada 1.jpg',
            'video_with_sound' => true,
            'position' => 'hero',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/banners/hero')
            ->assertOk()
            ->assertJsonPath('data.0.media_type', 'video')
            ->assertJsonPath('data.0.subtitle', 'Texto del banner')
            ->assertJsonPath('data.0.video_url', 'https://api.fathermotosport.com/storage/banners/videos/Promo%20Cascos.mp4')
            ->assertJsonPath('data.0.video_mobile_url', null)
            ->assertJsonPath('data.0.poster_url', 'https://api.fathermotosport.com/storage/banners/posters/Portada%201.jpg')
            ->assertJsonPath('data.0.video_with_sound', true)
            ->assertJsonPath('data.0.image_url', null);
    }

    public function test_the_new_position_returns_only_active_banners_in_order(): void
    {
        $crear = fn (string $titulo, int $orden, bool $activo = true) => Banner::create([
            'title' => $titulo,
            'image_url' => "banners/{$titulo}.png",
            'position' => 'home_after_categories',
            'is_active' => $activo,
            'sort_order' => $orden,
        ]);
        $crear('tercero', 3);
        $crear('primero', 1);
        $crear('oculto', 0, false);
        $crear('segundo', 2);
        Banner::create(['title' => 'del hero', 'image_url' => 'banners/h.png', 'position' => 'hero', 'is_active' => true]);

        $titulos = collect($this->getJson('/api/v1/banners/home_after_categories')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['primero', 'segundo', 'tercero'], $titulos);
    }

    // ───────────────── Limpieza de archivos ─────────────────

    public function test_replacing_the_video_deletes_the_old_file(): void
    {
        $banner = $this->bannerDeVideo();

        $banner->update(['video_path' => 'banners/videos/b.mp4']);

        Storage::disk('public')->assertMissing('banners/videos/a.mp4');
        Storage::disk('public')->assertExists(['banners/videos/a-movil.mp4', 'banners/posters/a.jpg']);
    }

    public function test_switching_to_image_in_the_panel_deletes_video_mobile_video_and_poster(): void
    {
        $banner = $this->bannerDeVideo();

        Livewire::test(EditBanner::class, ['record' => $banner->getRouteKey()])
            ->fillForm([
                'media_type' => 'image',
                'image_url' => [UploadedFile::fake()->image('nueva.png', 800, 800)],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $banner->refresh();
        $this->assertSame('image', $banner->media_type);
        $this->assertNull($banner->video_path);
        $this->assertNull($banner->video_mobile_path);
        $this->assertNull($banner->poster_path);
        $this->assertFalse($banner->video_with_sound);
        Storage::disk('public')->assertMissing(['banners/videos/a.mp4', 'banners/videos/a-movil.mp4', 'banners/posters/a.jpg']);
        Storage::disk('public')->assertExists($banner->image_url);
    }

    public function test_deleting_a_banner_deletes_all_its_files(): void
    {
        $banner = $this->bannerDeVideo();

        $banner->delete();

        Storage::disk('public')->assertMissing(['banners/videos/a.mp4', 'banners/videos/a-movil.mp4', 'banners/posters/a.jpg']);
    }

    public function test_a_file_used_by_another_banner_is_not_deleted(): void
    {
        $banner = $this->bannerDeVideo();
        $this->bannerDeVideo(['title' => 'Copia']);

        $banner->delete();

        Storage::disk('public')->assertExists(['banners/videos/a.mp4', 'banners/posters/a.jpg']);
    }

    // ───────────────── Panel y web ─────────────────

    public function test_the_panel_no_longer_offers_sidebar_but_keeps_it_on_old_banners(): void
    {
        Livewire::test(CreateBanner::class)
            ->assertFormFieldExists('position', fn ($campo) => ! array_key_exists('sidebar', $campo->getOptions())
                && $campo->getOptions()['home_after_categories'] === 'Debajo de categorías');

        $viejo = Banner::create(['title' => 'Viejo', 'image_url' => 'banners/v.png', 'position' => 'sidebar']);

        Livewire::test(EditBanner::class, ['record' => $viejo->getRouteKey()])
            ->assertFormSet(['position' => 'sidebar'])
            ->assertFormFieldExists('position', fn ($campo) => array_key_exists('sidebar', $campo->getOptions()));
    }

    public function test_saving_a_banner_asks_the_website_to_revalidate(): void
    {
        config(['services.revalidate.secret' => 'secreto', 'app.frontend_url' => 'https://fathermotosport.com']);
        Http::fake(['https://fathermotosport.com/api/revalidate' => Http::response(['revalidated' => true])]);

        Banner::create(['title' => 'Nuevo', 'image_url' => 'banners/n.png', 'position' => 'hero']);

        Http::assertSent(fn ($request) => $request->url() === 'https://fathermotosport.com/api/revalidate'
            && $request['tag'] === 'banners');
    }
}
