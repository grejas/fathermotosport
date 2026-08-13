<?php

namespace App\Http\Middleware;

use App\Support\AdminLoginThrottle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea con 429 las peticiones al panel de admin desde una IP que superó
 * el límite de intentos de login (ver App\Support\AdminLoginThrottle:
 * 5 intentos por minuto → bloqueo de 15 minutos).
 */
class AdminRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (AdminLoginThrottle::tooManyAttempts($ip)) {
            $minutes = (int) ceil(AdminLoginThrottle::blockedSecondsRemaining($ip) / 60);

            return response()->json([
                'message' => "Demasiados intentos de acceso. Probá de nuevo en {$minutes} minutos.",
            ], 429);
        }

        return $next($request);
    }
}
