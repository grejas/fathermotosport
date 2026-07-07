<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreConfigResource;
use App\Models\StoreConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreConfigController extends Controller
{
    /**
     * Configuración pública de la tienda (logo, favicon, contacto, redes).
     * No incluye credenciales de pago.
     */
    public function show(): JsonResource|JsonResponse
    {
        $config = StoreConfig::first();

        if (! $config) {
            return response()->json(['data' => null]);
        }

        return new StoreConfigResource($config);
    }
}
