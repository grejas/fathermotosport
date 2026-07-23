<?php

namespace App\Filament\Resources\VisorColorResource\Pages;

use App\Filament\Resources\VisorColorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisorColor extends EditRecord
{
    protected static string $resource = VisorColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
