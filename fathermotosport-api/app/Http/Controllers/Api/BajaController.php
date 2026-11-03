<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BajaDePromociones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Baja de las promociones por enlace firmado. GET solo consulta (los antivirus del correo
 * abren los enlaces solos: abrir no da de baja). POST da de baja: lo usan el botón de la
 * página /baja de la web y el "darse de baja" de Gmail o Outlook (List-Unsubscribe-Post).
 */
class BajaController extends Controller
{
    public function estado(Request $request): JsonResponse
    {
        $user = BajaDePromociones::usuarioDelEnlace($request->query('u'), $request->query('t'));

        if (! $user) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => BajaDePromociones::correoOculto($user->email),
            'unsubscribed' => $user->promo_unsubscribed_at !== null,
        ]);
    }

    public function confirmar(Request $request): JsonResponse
    {
        // u y t en la URL (un clic) o en el cuerpo (botón de la web).
        $user = BajaDePromociones::usuarioDelEnlace(
            $request->query('u', $request->input('u')),
            $request->query('t', $request->input('t')),
        );

        if (! $user) {
            return response()->json(['valid' => false], 404);
        }

        // El cliente de correo manda "List-Unsubscribe=One-Click" en el cuerpo.
        $origen = $request->input('List-Unsubscribe') === 'One-Click' ? 'one_click' : 'link';
        $referencia = $request->input('ref', $request->query('ref'));

        BajaDePromociones::darDeBaja($user, $origen, is_string($referencia) ? $referencia : null);

        return response()->json(['valid' => true, 'unsubscribed' => true]);
    }
}
