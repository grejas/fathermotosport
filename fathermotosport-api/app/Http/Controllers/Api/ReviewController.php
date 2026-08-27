<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * Listado global de reseñas aprobadas de TODOS los productos, más
     * recientes primero. Usado por la sección de reseñas de la home.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 12), 60);

        $reviews = Review::query()
            ->approved()
            ->with(['user:id,first_name,last_name,country', 'product:id,name,slug'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }

    /**
     * Rating promedio y distribución por estrella (1-5) entre TODAS las
     * reseñas aprobadas de todos los productos.
     */
    public function summary(): JsonResponse
    {
        $total = Review::approved()->count();
        $average = $total > 0 ? round((float) Review::approved()->avg('rating'), 1) : 0.0;

        $counts = Review::approved()
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $distribution = collect(range(5, 1))->map(fn (int $star) => [
            'rating' => $star,
            'count' => (int) ($counts[$star] ?? 0),
            'percent' => $total > 0 ? (int) round((($counts[$star] ?? 0) / $total) * 100) : 0,
        ])->values();

        return response()->json([
            'average' => $average,
            'total' => $total,
            'distribution' => $distribution,
        ]);
    }

    /**
     * Crea la reseña del usuario autenticado para el producto. Queda pendiente
     * de aprobación (is_approved = false) hasta que el admin la revise en el panel.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        // Respeta el constraint único (user_id, product_id) con un mensaje claro
        // en lugar de dejar que la excepción de BD llegue como un 500.
        $alreadyReviewed = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($alreadyReviewed) {
            // 'code' es machine-readable para que el frontend muestre su propio
            // mensaje traducido (i18n); 'message' se mantiene en español como
            // fallback para cualquier consumidor que no lo intercepte.
            return response()->json([
                'message' => 'Ya reseñaste este producto.',
                'code' => 'already_reviewed',
            ], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_approved' => false,
        ]);

        return (new ReviewResource($review->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
