<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * El autor es siempre el usuario autenticado. El Empleado, además, no
     * puede forzar un post como publicado ni fijar una fecha de publicación
     * aunque manipule el formulario: solo el Administrador puede hacerlo.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();

        if (! auth()->user()?->isAdmin()) {
            $data['status'] = 'draft';
            $data['published_at'] = null;
        } elseif (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Artículo creado correctamente';
    }
}
