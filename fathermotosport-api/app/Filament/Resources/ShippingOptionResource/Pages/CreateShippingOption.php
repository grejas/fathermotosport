<?php

namespace App\Filament\Resources\ShippingOptionResource\Pages;

use App\Filament\Resources\ShippingOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingOption extends CreateRecord
{
    protected static string $resource = ShippingOptionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
