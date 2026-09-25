<?php

namespace App\Filament\Resources\ShippingOptionResource\Pages;

use App\Filament\Resources\ShippingOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingOption extends EditRecord
{
    protected static string $resource = ShippingOptionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
