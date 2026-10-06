<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * Guardar vuelve al listado, que es lo habitual tras editar un producto. Para
     * quien sigue cargando fotos o tallas queda "Guardar y seguir editando".
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('saveAndContinue')
                ->label('Guardar y seguir editando')
                ->color('gray')
                ->action(fn () => $this->save(shouldRedirect: false))
                ->keyBindings(['mod+shift+s']),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Producto guardado correctamente';
    }
}
