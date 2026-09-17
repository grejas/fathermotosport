<?php

namespace App\Filament\Resources\FlashPromoResource\Pages;

use App\Filament\Resources\FlashPromoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlashPromo extends EditRecord
{
    protected static string $resource = FlashPromoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Promoción flash actualizada correctamente';
    }

    /**
     * Prellena el selector: si la promo aplica a toda la tienda (sin categorías),
     * muestra la opción "Todas las categorías" ya seleccionada; si tiene categorías,
     * muestra sus ids.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ids = $this->record->categories()->pluck('categories.id')->all();
        $data['category_ids'] = empty($ids) ? [FlashPromoResource::ALL_OPTION] : $ids;

        // Descompone rest_minutes en valor + unidad para el formulario.
        [$data['rest_value'], $data['rest_unit']] = FlashPromoResource::restMinutesToValueUnit($this->record->rest_minutes);

        // Descompone la duración guardada (ends_at - starts_at) en valor + unidad.
        [$data['duration_value'], $data['duration_unit']] = FlashPromoResource::durationToValueUnit($this->record);

        return $data;
    }

    /**
     * Calcula ends_at = starts_at + duración, y el descanso recurrente (rest_minutes).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = FlashPromoResource::applyDurationToData($data, $this->data);

        return FlashPromoResource::applyRecurringToData($data, $this->data);
    }

    /**
     * Sincroniza el pivote de categorías según el selector. "Todas las categorías"
     * (o vacío) => sin categorías = toda la tienda.
     */
    protected function afterSave(): void
    {
        FlashPromoResource::syncCategoriesFromState($this->record, $this->data['category_ids'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
