<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Role;
use App\Services\EmailService;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    /** Contraseña en texto plano para incluirla en el email de bienvenida. */
    private ?string $plainPassword = null;

    /** Si el email de bienvenida se envió correctamente (para la notificación). */
    private bool $welcomeEmailSent = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plainPassword = $data['password'] ?? null;

        $role = Role::where('slug', 'empleado')->firstOrFail();
        $data['role_id'] = $role->id;
        $data['status'] = ! empty($data['is_active']) ? 'active' : 'inactive';
        $data['last_password_change'] = now();
        unset($data['is_active']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->plainPassword) {
            return;
        }

        // EmailService::sendWelcomeEmployee ya loguea cualquier fallo (email
        // inválido, rebote al enviar, proveedor caído) sin lanzar excepción:
        // la creación del empleado nunca se ve afectada por eso.
        $this->welcomeEmailSent = app(EmailService::class)
            ->sendWelcomeEmployee($this->record, $this->plainPassword);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->welcomeEmailSent
            ? "Email de bienvenida enviado a {$this->record->email}"
            : 'Empleado creado. No se pudo enviar el email de bienvenida — revisá los logs.';
    }
}
