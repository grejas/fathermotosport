<?php

namespace App\Filament\Resources\FlashPromoResource\Pages;

use App\Filament\Resources\FlashPromoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFlashPromos extends ListRecords
{
    protected static string $resource = FlashPromoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
