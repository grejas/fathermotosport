<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\MailCampaign;
use App\Services\CampanaManual;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Crea la campaña como borrador. La clave dedupe_key sale del formulario abierto
 * (form_token): un doble clic en "Crear" no crea dos campañas, devuelve la misma.
 */
class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CampaignResource::accionPruebaDelFormulario(fn () => (array) ($this->data['payload'] ?? []))];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $token = (string) ($data['form_token'] ?? '') ?: (string) Str::uuid();
        unset($data['form_token']);

        return [
            ...$data,
            'type' => MailCampaign::TIPO_MANUAL,
            'status' => MailCampaign::BORRADOR,
            'dedupe_key' => 'manual:'.mb_substr($token, 0, 60),
            'payload' => CampanaManual::limpiarPayload($data['payload'] ?? []),
            'created_by' => auth()->id(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            return MailCampaign::where('dedupe_key', $data['dedupe_key'])->firstOrFail();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
