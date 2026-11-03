<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pide a la web (Next.js) que descarte su caché de un tag, para que un cambio del
 * panel se vea sin esperar al revalidate por tiempo ni hacer un redeploy.
 *
 * Nunca lanza: si la web no responde, el guardado ya ocurrió y la caché igual se
 * renueva sola a los 10 minutos.
 *
 * Los logs llevan solo el tag, la URL sin parámetros y el código de estado: nunca el
 * secreto, la cabecera, el cuerpo de la respuesta ni el mensaje de una excepción (el de
 * una cabecera inválida incluye su valor).
 */
class FrontendRevalidator
{
    public function revalidate(string $tag): bool
    {
        // Un REVALIDATE_SECRET con \r o \n final (un .env editado en Windows) dejaría la
        // cabecera inválida: se limpia antes de usarlo.
        $secret = trim((string) config('services.revalidate.secret'));
        $url = rtrim((string) config('app.frontend_url'), '/').'/api/revalidate';
        $contexto = ['tag' => $tag, 'url' => self::urlParaLog($url)];

        if ($secret === '') {
            Log::error('Revalidación de la web omitida: falta REVALIDATE_SECRET en el .env.', $contexto);

            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url, ['tag' => $tag]);

            if ($response->successful()) {
                return true;
            }

            Log::error('La web rechazó la revalidación.', [...$contexto, 'status' => $response->status()]);
        } catch (ConnectionException) {
            Log::error('No se pudo contactar a la web para revalidar (sin respuesta: timeout o conexión).', [...$contexto, 'status' => null]);
        } catch (Throwable) {
            Log::error('No se pudo pedir la revalidación a la web (falló antes de recibir respuesta).', [...$contexto, 'status' => null]);
        }

        return false;
    }

    /** Esquema, host, puerto y ruta: sin usuario, contraseña, parámetros ni fragmento. */
    private static function urlParaLog(string $url): string
    {
        $partes = parse_url($url);
        if ($partes === false || ! isset($partes['host'])) {
            return '(FRONTEND_URL no válida)';
        }

        return ($partes['scheme'] ?? 'https').'://'.$partes['host']
            .(isset($partes['port']) ? ':'.$partes['port'] : '')
            .($partes['path'] ?? '');
    }
}
