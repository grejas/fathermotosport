<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprime con gzip las respuestas de texto (HTML/JSON) cuando el cliente lo
 * acepta. Reduce el tamaño de transferencia del panel y de la API.
 * (Laravel no trae un GzipResponse propio; este es equivalente y seguro.)
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nunca tocar el panel admin ni Livewire (rompería los POST del login).
        if ($request->is('admin/*') || $request->is('livewire/*')) {
            return $next($request);
        }

        $response = $next($request);

        if (! function_exists('gzencode')) {
            return $response;
        }

        // El cliente debe aceptar gzip.
        if (! str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return $response;
        }

        // No recomprimir respuestas ya codificadas ni streams/descargas.
        if ($response->headers->has('Content-Encoding')
            || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < 1024) {
            return $response; // no vale la pena comprimir respuestas pequeñas
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if (! preg_match('#text/|application/(json|javascript|xml)#i', $contentType)) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $response;
    }
}
