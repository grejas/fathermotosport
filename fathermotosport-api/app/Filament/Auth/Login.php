<?php

namespace App\Filament\Auth;

use App\Support\AdminLoginThrottle;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Extiende el login de Filament para contar los intentos por IP y aplicar
 * el bloqueo de 15 minutos (ver App\Support\AdminLoginThrottle). Este es el
 * único punto que ve cada intento real, ya que el submit corre por Livewire.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $ip = request()->ip();

        if (AdminLoginThrottle::tooManyAttempts($ip)) {
            $minutes = (int) ceil(AdminLoginThrottle::blockedSecondsRemaining($ip) / 60);

            Notification::make()
                ->title('Demasiados intentos de acceso')
                ->body("Por seguridad, esperá {$minutes} minutos antes de volver a intentar.")
                ->danger()
                ->send();

            return null;
        }

        // Se cuenta antes de validar: si falla (excepción o null), el
        // contador queda incrementado; solo se limpia si hay éxito.
        AdminLoginThrottle::hit($ip);

        $response = parent::authenticate();

        if ($response !== null) {
            AdminLoginThrottle::clear($ip);
        }

        return $response;
    }
}
