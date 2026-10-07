<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Filament\Resources\UpsellRuleResource;
use App\Services\UpsellService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUpsellRule extends CreateRecord
{
    use GuardaReglas;

    protected static string $resource = UpsellRuleResource::class;

    /** Una regla por producto ofrecido, omitiendo las que ya existen. */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->guardar(fn () => app(UpsellService::class)->guardarReglas(UpsellRuleResource::datosParaGuardar($data)));
    }

    protected function getCreatedNotification(): ?Notification
    {
        return $this->avisoDeGuardado('creada', 'creadas');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
