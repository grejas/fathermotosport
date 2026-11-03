<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ReviewImage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve las fotos de reseñas al panel, también las pendientes (disco privado, sin URL
 * pública). Solo para el staff con sesión en el panel; para cualquier otro, 404.
 */
class FotoDeResenaController extends Controller
{
    public function __invoke(Request $request, ReviewImage $foto, string $variante): StreamedResponse
    {
        abort_unless((bool) $request->user()?->isStaff(), 404);

        $ruta = $variante === 'miniatura' ? $foto->thumb_path : $foto->path;
        abort_unless($ruta && $foto->disco()->exists($ruta), 404);

        return $foto->disco()->response($ruta, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
