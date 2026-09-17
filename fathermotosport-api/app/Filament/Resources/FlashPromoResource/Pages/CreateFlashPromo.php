<?php

namespace App\Filament\Resources\FlashPromoResource\Pages;

use App\Filament\Resources\FlashPromoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFlashPromo extends CreateRecord
{
    protected static string $resource = FlashPromoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Promoción flash creada correctamente';
    }

    /**
     * Calcula ends_at = starts_at + duración, y el descanso recurrente (rest_minutes).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = FlashPromoResource::applyDurationToData($data, $this->data);

        return FlashPromoResource::applyRecurringToData($data, $this->data);
    }

    /**
     * Sincroniza el pivote de categorías según el selector. "Todas las categorías"
     * (o vacío) => sin categorías = toda la tienda.
     */
    protected function afterCreate(): void
    {
        FlashPromoResource::syncCategoriesFromState($this->record, $this->data['category_ids'] ?? []);
    }
}
