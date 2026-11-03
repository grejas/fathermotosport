<?php

namespace Tests\Feature;

use App\Filament\Resources\ReviewResource;
use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Support\FotoDeResena;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Aprobar todo" en el panel de reseñas: la reseña (is_approved) y sus fotos pendientes
 * (del disco privado al público) en una sola acción, desde la ventana "Ver", la fila o
 * varias seleccionadas. Una foto que no se puede mover se queda pendiente y entera.
 */
class ReviewApproveAllTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function png(): UploadedFile
    {
        $gd = imagecreatetruecolor(30, 30);
        ob_start();
        imagepng($gd);
        $ruta = tempnam(sys_get_temp_dir(), 'resena').'.png';
        file_put_contents($ruta, (string) ob_get_clean());

        return new UploadedFile($ruta, 'foto.png', 'image/png', null, true);
    }

    /** Reseña con fotos subidas como las sube la API (pendientes en el disco privado). */
    private function resena(bool $aprobada, int $pendientes = 0, int $aprobadas = 0): Review
    {
        $review = Review::factory()->create([
            'user_id' => User::factory()->create()->id,
            'product_id' => Product::factory()->create(['is_active' => true])->id,
            'is_approved' => $aprobada,
        ]);
        for ($i = 0; $i < $aprobadas + $pendientes; $i++) {
            $foto = $review->images()->create([...FotoDeResena::guardarPendiente($this->png()), 'sort_order' => $i]);
            if ($i < $aprobadas) {
                $foto->aprobar();
            }
        }

        return $review;
    }

    private function assertTodoAprobado(Review $review): void
    {
        $this->assertTrue($review->fresh()->is_approved);
        foreach ($review->images()->get() as $foto) {
            $this->assertSame(ReviewImage::APROBADA, $foto->status);
            $this->assertNotNull($foto->approved_at);
            Storage::disk('public')->assertExists([$foto->path, $foto->thumb_path]);
        }
    }

    public function test_approve_all_from_the_view_modal(): void
    {
        $review = $this->resena(aprobada: false, pendientes: 2);

        Livewire::test(ListReviews::class)
            ->mountTableAction('ver', $review)
            ->assertSeeHtml("aprobarTodo('{$review->id}')")
            ->assertSeeHtml("cambiarAprobacion('{$review->id}', true)")
            ->assertSeeHtml("aprobarTodasLasFotos('{$review->id}')")
            ->call('aprobarTodo', $review->id)
            ->assertNotified('Aprobado: 1 reseña, 2 fotos')
            ->assertDontSeeHtml("aprobarTodo('{$review->id}')");

        $this->assertTodoAprobado($review);
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertCount(4, Storage::disk('public')->allFiles('resenas'));
    }

    public function test_approve_all_from_the_row_and_its_visibility(): void
    {
        $pendiente = $this->resena(aprobada: true, pendientes: 1);
        $sinAprobar = $this->resena(aprobada: false);
        $alDia = $this->resena(aprobada: true, aprobadas: 1);

        Livewire::test(ListReviews::class)
            ->assertTableActionVisible('aprobar_todo', $pendiente)
            ->assertTableActionVisible('aprobar_todo', $sinAprobar)
            ->assertTableActionHidden('aprobar_todo', $alDia)
            ->callTableAction('aprobar_todo', $pendiente)
            ->assertNotified('Aprobado: 0 reseñas, 1 foto')
            ->assertTableActionHidden('aprobar_todo', $pendiente);

        $this->assertTodoAprobado($pendiente);
        $this->assertFalse($sinAprobar->fresh()->is_approved);
    }

    public function test_bulk_approve_all_asks_for_confirmation_and_counts_reviews_and_photos(): void
    {
        $a = $this->resena(aprobada: false, pendientes: 2);
        $b = $this->resena(aprobada: false);
        $c = $this->resena(aprobada: true, pendientes: 1, aprobadas: 1);
        $alDia = $this->resena(aprobada: true, aprobadas: 1);
        $fuera = $this->resena(aprobada: false, pendientes: 1);

        // Al pulsarla se abre la confirmación y todavía no se aprueba nada.
        Livewire::test(ListReviews::class)
            ->mountTableBulkAction('aprobar_todo', [$a, $b, $c, $alDia])
            ->assertTableBulkActionMounted('aprobar_todo')
            ->assertSee('Se aprueban las reseñas y todas sus fotos pendientes')
            ->tap(fn () => $this->assertFalse($a->fresh()->is_approved))
            ->callMountedTableBulkAction()
            ->assertNotified('Aprobado: 2 reseñas, 3 fotos');

        foreach ([$a, $b, $c, $alDia] as $review) {
            $this->assertTodoAprobado($review);
        }
        $this->assertFalse($fuera->fresh()->is_approved);
        $this->assertSame(ReviewImage::PENDIENTE, $fuera->images()->firstOrFail()->status);
    }

    public function test_a_review_without_photos_is_approved(): void
    {
        $review = $this->resena(aprobada: false);

        $this->assertSame(['resena' => true, 'fotos' => 0, 'fallidas' => []], $review->aprobarTodo());
        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_photos_already_approved_are_left_as_they_are(): void
    {
        $review = $this->resena(aprobada: true, pendientes: 1, aprobadas: 1);
        $yaAprobada = $review->images()->firstOrFail();
        $this->travel(1)->hours();

        $this->assertSame(['resena' => false, 'fotos' => 1, 'fallidas' => []], $review->aprobarTodo());
        $this->assertEquals($yaAprobada->approved_at, $yaAprobada->fresh()->approved_at);
        $this->assertSame($yaAprobada->path, $yaAprobada->fresh()->path);
        $this->assertTodoAprobado($review);

        // Ya no queda nada: otra vez no cambia nada.
        Livewire::test(ListReviews::class)
            ->call('aprobarTodo', $review->id)
            ->assertNotified('No había nada pendiente');
    }

    public function test_a_missing_file_leaves_that_photo_pending_and_approves_the_rest(): void
    {
        $review = $this->resena(aprobada: false, pendientes: 3);
        [$uno, $dos, $tres] = $review->images()->get()->all();
        // A la 2 le falta la miniatura: la grande ya se habrá copiado cuando falle.
        Storage::disk('local')->delete($dos->thumb_path);

        Livewire::test(ListReviews::class)->call('aprobarTodo', $review->id);

        // Se avisa cuál falló, y el aviso queda hasta cerrarlo.
        $aviso = collect(session('filament.notifications'))->last();
        $this->assertSame('Aprobado: 1 reseña, 2 fotos', $aviso['title']);
        $this->assertSame('warning', $aviso['status']);
        $this->assertSame('persistent', $aviso['duration']);
        $this->assertStringContainsString('Foto 2', $aviso['body']);

        $this->assertTrue($review->fresh()->is_approved);
        foreach ([$uno, $tres] as $foto) {
            $this->assertSame(ReviewImage::APROBADA, $foto->fresh()->status);
            Storage::disk('public')->assertExists([$foto->fresh()->path, $foto->fresh()->thumb_path]);
        }

        // La 2 sigue pendiente, en el privado, sin copias sueltas en el público.
        $dos = $dos->fresh();
        $this->assertSame(ReviewImage::PENDIENTE, $dos->status);
        $this->assertNull($dos->approved_at);
        Storage::disk('local')->assertExists($dos->path);
        Storage::disk('public')->assertMissing(FotoDeResena::CARPETA_PUBLICA.'/'.basename($dos->path));
        $this->assertCount(4, Storage::disk('public')->allFiles('resenas'));

        // Volver a intentarlo no cambia nada, y la API solo publica las dos buenas.
        $this->assertSame(['resena' => false, 'fotos' => 0, 'fallidas' => ['Foto 2']], $review->fresh()->aprobarTodo());
        $this->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data.0.images');
    }

    public function test_a_database_error_undoes_everything_and_keeps_the_files_private(): void
    {
        $review = $this->resena(aprobada: false, pendientes: 2);
        $segunda = $review->images()->get()->last();
        ReviewImage::updating(function (ReviewImage $foto) use ($segunda) {
            if ($foto->is($segunda)) {
                throw new QueryException('sqlite', 'update review_images', [], new \Exception('falla de la base'));
            }
        });

        try {
            $review->aprobarTodo();
            $this->fail('Debía fallar.');
        } catch (QueryException) {
        }

        $this->assertFalse($review->fresh()->is_approved);
        $this->assertSame(0, $review->images()->where('status', ReviewImage::APROBADA)->count());
        foreach ($review->images()->get() as $foto) {
            Storage::disk('local')->assertExists([$foto->path, $foto->thumb_path]);
        }
        $this->assertEmpty(Storage::disk('public')->allFiles('resenas'));
    }

    public function test_the_menu_counter_goes_down_after_approving_all(): void
    {
        $a = $this->resena(aprobada: false, pendientes: 2);
        $this->resena(aprobada: true, pendientes: 1);
        $this->assertSame('4', ReviewResource::getNavigationBadge());

        $pagina = Livewire::test(ListReviews::class)->assertSet('contadorDelMenu', 4);
        $pagina->call('aprobarTodo', $a->id)->assertSet('contadorDelMenu', 1);

        $this->assertSame('1', ReviewResource::getNavigationBadge());
        $js = json_encode(\Livewire\invade($pagina)->lastState->getEffects()['xjs'] ?? []);
        $this->assertStringContainsString('const pendientes = 1;', $js);

        $pagina->callTableBulkAction('aprobar_todo', Review::all())->assertSet('contadorDelMenu', 0);
        $this->assertNull(ReviewResource::getNavigationBadge());
    }
}
