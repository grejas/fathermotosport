<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Services\VariantesDeImagen;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Imagen achicada y en WebP, generada la primera vez que se pide.
 *
 * URL: /storage/v/{ancho}/{ruta original}.webp — por ejemplo
 * /storage/v/640/products/casco.jpg.webp sale de storage/app/public/products/casco.jpg.
 *
 * El archivo se guarda en storage/app/public/v/... (que es public/storage/v/...), así que
 * desde la segunda vez lo sirve Apache directo, sin pasar por PHP: Laravel solo atiende
 * la primera (el .htaccess manda a index.php lo que no existe).
 *
 * Reemplaza al optimizador de Vercel, que cobra por imagen y se quedó sin cuota.
 */
class VarianteDeImagenController extends Controller
{
    public function __invoke(VariantesDeImagen $variantes, int $ancho, string $ruta): BinaryFileResponse
    {
        $archivo = $variantes->generar($ancho, $ruta);

        abort_if($archivo === null, 404);

        return response()->file($archivo, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
