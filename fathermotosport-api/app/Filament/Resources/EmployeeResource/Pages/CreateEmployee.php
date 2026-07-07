<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Mail\WelcomeEmployeeMail;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    /** Contraseña en texto plano para incluirla en el email de bienvenida. */
    private ?string $plainPassword = null;

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
        if ($this->plainPassword) {
            // Email de bienvenida con las credenciales (Resend / driver configurado).
            Mail::to($this->record->email)
                ->send(new WelcomeEmployeeMail($this->record, $this->plainPassword));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Empleado creado y notificado por email.';
    }
}
