<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlashPromo;
use Illuminate\Http\JsonResponse;

class FlashPromoController extends Controller
{
    /**
     * Todas las promociones flash vigentes AHORA (array).
     *
     * Cada item incluye: id, promo_text, ends_at (ISO), category_ids, category_slugs
     * y applies_to_all (true => promo de toda la tienda). El frontend decide cuál
     * mostrar (carrusel en home, match de categoría en producto).
     */
    public function show(): JsonResponse
    {
        $promos = FlashPromo::activeNowList()
            ->map(fn (FlashPromo $promo) => $promo->toPublicArray())
            ->values();

        return response()->json([
            'success' => true,
            'data' => $promos,
        ]);
    }
}
