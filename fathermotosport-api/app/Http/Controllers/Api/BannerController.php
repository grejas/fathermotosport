<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * Todos los banners activos, ordenados por sort_order.
     */
    public function index(): JsonResponse
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BannerResource::collection($banners),
        ]);
    }

    /**
     * Banners activos de una posición concreta (hero, home_after_categories, footer).
     * A igual sort_order, el más antiguo primero: el orden no cambia entre cargas.
     */
    public function byPosition(string $position): JsonResponse
    {
        $banners = Banner::where('is_active', true)
            ->where('position', $position)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BannerResource::collection($banners),
        ]);
    }
}
