<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\MailCampaign;
use App\Services\CampanaManual;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Solo un borrador se edita (CampaignResource::canEdit); lo enviado o en curso se duplica. */
class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CampaignResource::accionPruebaDelFormulario(fn () => (array) ($this->data['payload'] ?? []))];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['form_token']);
        $payload = $data['payload'] ?? [];
        // El formulario solo trae los idiomas activos (español): el contenido en pt/en de
        // una campaña antigua se conserva tal cual.
        $payload['contenido'] = array_replace($this->getRecord()->payload['contenido'] ?? [], $payload['contenido'] ?? []);
        $data['payload'] = CampanaManual::limpiarPayload($payload);

        return $data;
    }

    protected function beforeSave(): void
    {
        // Otro administrador pudo programarla o enviarla con este formulario abierto.
        if (MailCampaign::whereKey($this->getRecord()->getKey())->value('status') !== MailCampaign::BORRADOR) {
            Notification::make()->title('Ya no es un borrador')
                ->body('La campaña se programó o se envió mientras la editabas: duplícala para cambiarla.')->danger()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
