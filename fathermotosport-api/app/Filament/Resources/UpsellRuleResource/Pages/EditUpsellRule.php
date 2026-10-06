<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Filament\Resources\UpsellRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUpsellRule extends EditRecord
{
    protected static string $resource = UpsellRuleResource::class;

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
