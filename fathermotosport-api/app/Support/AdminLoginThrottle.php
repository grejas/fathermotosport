<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limiting del login del panel de Filament, por IP: máximo 5 intentos
 * por minuto; al superarlos, esa IP queda bloqueada 15 minutos.
 *
 * El conteo de intentos vive acá (en vez de solo en el middleware) porque el
 * submit del formulario de login de Filament corre por Livewire
 * (POST a /livewire/update), no por una request tradicional a la ruta del
 * panel — App\Filament\Auth\Login::authenticate() es el único punto que ve
 * cada intento real. AdminRateLimiter (middleware) solo aplica el bloqueo
 * y devuelve el 429.
 */
class AdminLoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPTS_DECAY_SECONDS = 60;

    private const BLOCK_MINUTES = 15;

    /** Registra un intento fallido; bloquea la IP si superó el máximo. */
    public static function hit(string $ip): void
    {
        $attemptsKey = self::attemptsKey($ip);
        RateLimiter::hit($attemptsKey, self::ATTEMPTS_DECAY_SECONDS);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_ATTEMPTS)) {
            Cache::put(
                self::blockedKey($ip),
                now()->addMinutes(self::BLOCK_MINUTES)->timestamp,
                now()->addMinutes(self::BLOCK_MINUTES)
            );
        }
    }

    /** Limpia el contador (login exitoso). */
    public static function clear(string $ip): void
    {
        RateLimiter::clear(self::attemptsKey($ip));
        Cache::forget(self::blockedKey($ip));
    }

    public static function tooManyAttempts(string $ip): bool
    {
        return self::blockedSecondsRemaining($ip) > 0;
    }

    public static function blockedSecondsRemaining(string $ip): int
    {
        $until = Cache::get(self::blockedKey($ip));

        if (! $until) {
            return 0;
        }

        return max(0, $until - now()->timestamp);
    }

    private static function attemptsKey(string $ip): string
    {
        return "admin-login-attempts:{$ip}";
    }

    private static function blockedKey(string $ip): string
    {
        return "admin-login-blocked:{$ip}";
    }
}
