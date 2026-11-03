<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingReturnsSetting;
use App\Models\StoreConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingReturnsController extends Controller
{
    /**
     * Textos de envío y devoluciones para ?locale=es|pt|en (otro valor => es).
     *
     * Los textos vacíos llegan como null: la web usa sus textos por defecto. El
     * contacto son el email y el whatsapp (solo dígitos) de StoreConfig::datosPublicos(),
     * la misma lista cerrada de GET /store/settings.
     */
    public function show(Request $request): JsonResponse
    {
        $locale = in_array($request->query('locale'), ShippingReturnsSetting::LOCALES, true)
            ? $request->query('locale')
            : 'es';

        $setting = Cache::rememberForever(
            ShippingReturnsSetting::CACHE_KEY,
            fn () => ShippingReturnsSetting::current() ?? new ShippingReturnsSetting
        );

        $publico = StoreConfig::datosPublicos();

        return response()->json([
            'success' => true,
            'data' => [
                'locale' => $locale,
                ...$setting->toPublicArray($locale),
                'contact' => [
                    'email' => $publico['email'],
                    'whatsapp' => $publico['whatsapp'],
                ],
            ],
        ]);
    }
}
