<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pide a la web (Next.js) que descarte su caché de un tag, para que un cambio del
 * panel se vea sin esperar al revalidate por tiempo ni hacer un redeploy.
 *
 * Nunca lanza: si la web no responde, el guardado ya ocurrió y la caché igual se
 * renueva sola a los 10 minutos.
 */
class FrontendRevalidator
{
    public function revalidate(string $tag): bool
    {
        $secret = (string) config('services.revalidate.secret');
        $url = rtrim((string) config('app.frontend_url'), '/').'/api/revalidate';

        if ($secret === '') {
            Log::warning('Revalidación de la web omitida: falta REVALIDATE_SECRET.', ['tag' => $tag]);

            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url, ['tag' => $tag]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('La web rechazó la revalidación.', ['tag' => $tag, 'status' => $response->status()]);
        } catch (Throwable $e) {
            Log::warning('No se pudo contactar a la web para revalidar.', ['tag' => $tag, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
