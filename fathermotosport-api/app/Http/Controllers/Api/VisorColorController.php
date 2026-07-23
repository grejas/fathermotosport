<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VisorColorResource;
use App\Models\VisorColor;
use Illuminate\Http\JsonResponse;

class VisorColorController extends Controller
{
    /**
     * Todos los colores de visor activos, ordenados por sort_order.
     */
    public function index(): JsonResponse
    {
        $visorColors = VisorColor::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => VisorColorResource::collection($visorColors),
        ]);
    }
}
