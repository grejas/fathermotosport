<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Filament\Resources\UpsellRuleResource;
use App\Models\UpsellRule;
use App\Services\UpsellService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUpsellRule extends EditRecord
{
    use GuardaReglas;

    protected static string $resource = UpsellRuleResource::class;

    /**
     * Igual que al crear: esta regla se queda con su producto (o el primero elegido) y
     * cada producto agregado es una regla nueva con el mismo disparador.
     *
     * @param  UpsellRule  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->guardar(fn () => app(UpsellService::class)->guardarReglas(UpsellRuleResource::datosParaGuardar($data), $record));
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->avisoDeGuardado('guardada', 'guardadas', cuentaLaEditada: true);
    }

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
