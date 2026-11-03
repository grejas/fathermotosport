<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use App\Models\ReviewImage;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Js;
use Livewire\Attributes\Locked;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /*
    | Botones de la ventana "Ver" (resources/views/filament/resenas/detalle.blade.php).
    | Son métodos de Livewire: se vuelve a comprobar el acceso en cada uno.
    */

    public function aprobarTodo(string $reviewId): void
    {
        abort_unless(ReviewResource::canAccess(), 403);

        $hecho = Review::findOrFail($reviewId)->aprobarTodo();
        ReviewResource::avisarAprobacion((int) $hecho['resena'], $hecho['fotos'], $hecho['fallidas']);
    }

    public function aprobarFoto(string $fotoId): void
    {
        abort_unless(ReviewResource::canAccess(), 403);

        try {
            ReviewImage::findOrFail($fotoId)->aprobar();
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('No se pudo aprobar la foto')
                ->body('Su archivo falta o no se puede leer. Sigue pendiente y sin publicar.')
                ->danger()->persistent()->send();

            return;
        }
        Notification::make()->title('Foto aprobada')->success()->send();
    }

    public function eliminarFoto(string $fotoId): void
    {
        abort_unless(ReviewResource::canAccess(), 403);

        ReviewImage::findOrFail($fotoId)->delete();
        Notification::make()->title('Foto eliminada')->success()->send();
    }

    public function aprobarTodasLasFotos(string $reviewId): void
    {
        abort_unless(ReviewResource::canAccess(), 403);

        $hecho = Review::findOrFail($reviewId)->aprobarFotosPendientes();
        ReviewResource::avisarAprobacion(0, $hecho['fotos'], $hecho['fallidas']);
    }

    public function cambiarAprobacion(string $reviewId, bool $aprobada): void
    {
        abort_unless(ReviewResource::canAccess(), 403);

        Review::findOrFail($reviewId)->update(['is_approved' => $aprobada]);
        Notification::make()->title($aprobada ? 'Reseña aprobada' : 'Reseña retirada')->success()->send();
    }

    /*
    | Contador del menú. El menú lateral no se vuelve a dibujar con las acciones de esta
    | página: tras cada acción que lo cambie (aprobar, retirar, eliminar, fila, masiva o
    | ventana "Ver") se corrige el número en el navegador.
    */

    #[Locked]
    public ?int $contadorDelMenu = null;

    public function rendered(): void
    {
        $pendientes = ReviewResource::contarPendientes();

        if ($this->contadorDelMenu !== null && $pendientes !== $this->contadorDelMenu) {
            $this->js('(() => {
                const ruta = new URL('.Js::from(ReviewResource::getUrl()).').pathname;
                const pendientes = '.$pendientes.';
                document.querySelectorAll(".fi-sidebar-item > a").forEach((enlace) => {
                    if (enlace.pathname !== ruta) return;
                    const insignia = enlace.querySelector(".fi-badge");
                    if (!insignia) return;
                    if (pendientes === 0) insignia.parentElement.remove();
                    else (insignia.querySelector(".truncate") ?? insignia).textContent = String(pendientes);
                });
            })()');
        }

        $this->contadorDelMenu = $pendientes;
    }
}
