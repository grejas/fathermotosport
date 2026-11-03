<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AvisoDescuento;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CategoryResource::tallasAlFormulario($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CategoryResource::tallasDelFormulario($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->accionAvisar(),
            $this->accionReenviar(),
            $this->accionProbar(),
            Actions\DeleteAction::make(),
        ];
    }

    /** Lo que se guardó: el aviso usa la categoría de la base, no el formulario sin guardar. */
    private function categoria(): Category
    {
        return Category::findOrFail($this->record->getKey());
    }

    /** Solo el administrador manda correos a los clientes; el interruptor tiene que estar encendido. */
    private function puedeAvisar(): bool
    {
        return (bool) auth()->user()?->isAdmin() && $this->categoria()->notify_customers;
    }

    /** Estado, números y vista previa en es/pt/en (antes de confirmar). */
    private function vistaPrevia()
    {
        $avisos = app(AvisoDescuento::class);
        $categoria = $this->categoria();

        return view('filament.avisos.vista-previa', [
            'motivo' => $avisos->motivoParaNoEnviar($categoria),
            'existente' => $avisos->avisoExistente($categoria),
            'destinatarios' => $avisos->destinatarios()->count(),
            'sinEvidencia' => $avisos->excluidosSinEvidencia(),
            'previas' => collect(['es', 'pt', 'en'])->mapWithKeys(fn ($i) => [$i => $avisos->correoDePrueba($categoria, $i)->render()])->all(),
        ]);
    }

    private function accionAvisar(): Actions\Action
    {
        return Actions\Action::make('avisar_por_correo')
            ->label('Avisar por correo')
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->visible(fn () => $this->puedeAvisar())
            ->modalHeading('Avisar a los clientes por correo')
            ->modalWidth('4xl')
            ->modalContent(fn () => $this->vistaPrevia())
            ->form(function () {
                $avisos = app(AvisoDescuento::class);
                $categoria = $this->categoria();
                if ($avisos->motivoParaNoEnviar($categoria) || $avisos->avisoExistente($categoria)) {
                    return [];
                }

                $n = $avisos->destinatarios()->count();

                return [
                    Forms\Components\Checkbox::make('confirmo')
                        ->label("Voy a enviar este correo a {$n} clientes")
                        ->accepted()
                        ->validationMessages(['accepted' => 'Marca la casilla para confirmar el envío.']),
                ];
            })
            ->modalSubmitAction(function ($action) {
                $avisos = app(AvisoDescuento::class);
                $categoria = $this->categoria();

                return $avisos->motivoParaNoEnviar($categoria) || $avisos->avisoExistente($categoria)
                    ? false
                    : $action->label('Enviar aviso');
            })
            ->modalCancelActionLabel('Cerrar')
            ->action(fn () => $this->registrarAviso(reenvio: false));
    }

    private function accionReenviar(): Actions\Action
    {
        return Actions\Action::make('reenviar_aviso')
            ->label('Reenviar')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn () => $this->puedeAvisar() && app(AvisoDescuento::class)->avisoExistente($this->categoria()))
            ->modalHeading('Reenviar el aviso de este descuento')
            ->modalWidth('4xl')
            ->modalContent(fn () => $this->vistaPrevia())
            ->form(fn () => [
                Forms\Components\Checkbox::make('confirmo')
                    ->label('Voy a enviar este correo OTRA VEZ a '.app(AvisoDescuento::class)->destinatarios()->count().' clientes, aunque ya lo hayan recibido')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Marca la casilla para confirmar el reenvío.']),
            ])
            ->modalSubmitActionLabel('Reenviar aviso')
            ->action(fn () => $this->registrarAviso(reenvio: true));
    }

    private function accionProbar(): Actions\Action
    {
        return Actions\Action::make('probar_aviso')
            ->label('Enviar prueba')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn () => $this->puedeAvisar())
            ->modalHeading('Enviar el aviso de prueba')
            ->modalDescription('Solo a este correo: no cuenta en el cupo ni queda como destinatario del aviso.')
            ->form([
                Forms\Components\TextInput::make('email')->label('Correo')->email()->required(),
                Forms\Components\Select::make('idioma')->label('Idioma')
                    ->options(['es' => 'Español', 'pt' => 'Português', 'en' => 'English'])->default('es')->required(),
            ])
            ->modalSubmitActionLabel('Enviar')
            ->action(function (array $data) {
                try {
                    Mail::to($data['email'])->send(app(AvisoDescuento::class)->correoDePrueba($this->categoria(), $data['idioma']));
                    Notification::make()->title("Prueba enviada a {$data['email']}")->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('No se pudo enviar la prueba')->body('Revisa storage/logs/laravel.log.')->danger()->send();
                }
            });
    }

    private function registrarAviso(bool $reenvio): void
    {
        try {
            $campana = app(AvisoDescuento::class)->crear($this->categoria(), auth()->user(), $reenvio);
        } catch (RuntimeException $e) {
            Notification::make()->title('No se envió el aviso')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($reenvio ? 'Reenvío registrado' : 'Aviso registrado')
            ->body("Se envía por tandas a {$campana->total_count} clientes, respetando el cupo de Resend. El avance está en Clientes > Avisos por correo.")
            ->success()
            ->send();
    }
}
