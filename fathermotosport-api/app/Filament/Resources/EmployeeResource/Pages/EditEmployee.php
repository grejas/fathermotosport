<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    /** Rellena el toggle is_active a partir del status al abrir el formulario. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['is_active'] = ($data['status'] ?? 'active') === 'active';

        return $data;
    }

    /** Mapea el toggle is_active de vuelta al enum status al guardar. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['status'] = ! empty($data['is_active']) ? 'active' : 'inactive';
        unset($data['is_active']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
