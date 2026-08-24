<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Refuerzo de servidor: el Empleado nunca puede publicar (ni cambiar la
     * fecha de publicación) editando su propio borrador, aunque manipule el
     * formulario. Solo el Administrador puede publicar.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->isAdmin()) {
            $data['status'] = $this->record->status;
            $data['published_at'] = $this->record->published_at;
        } elseif (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
