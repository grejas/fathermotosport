<?php

namespace App\Filament\Resources\MailCampaignResource\Pages;

use App\Filament\Resources\MailCampaignResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMailCampaign extends ViewRecord
{
    protected static string $resource = MailCampaignResource::class;

    public function getTitle(): string
    {
        return (string) $this->getRecord()->name;
    }
}
