<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Filament\Resources\UpsellRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUpsellRule extends CreateRecord
{
    protected static string $resource = UpsellRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
