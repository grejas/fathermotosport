<?php

namespace App\Filament\Resources\VisorColorResource\Pages;

use App\Filament\Resources\VisorColorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisorColors extends ListRecords
{
    protected static string $resource = VisorColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
