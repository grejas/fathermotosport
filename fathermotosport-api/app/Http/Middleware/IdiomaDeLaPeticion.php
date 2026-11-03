<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idioma de los mensajes de la API (validación del registro, avisos): la cabecera
 * X-Locale que manda la web (es, pt o en). Sin cabecera o con otro valor, español.
 */
class IdiomaDeLaPeticion
{
    public const IDIOMAS = ['es', 'pt', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::desde($request) ?? 'es');

        return $next($request);
    }

    /** Idioma pedido por la web, o null si no mandó uno válido. */
    public static function desde(Request $request): ?string
    {
        $idioma = strtolower(substr(trim((string) $request->header('X-Locale')), 0, 2));

        return in_array($idioma, self::IDIOMAS, true) ? $idioma : null;
    }
}
