<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreConfig;
use Illuminate\Http\JsonResponse;

class StoreSettingsController extends Controller
{
    /**
     * Datos públicos de "Configuración de la tienda": nombre, correo, teléfono,
     * WhatsApp (solo dígitos) y redes. Lista cerrada en StoreConfig::datosPublicos().
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StoreConfig::datosPublicos(),
        ]);
    }
}
