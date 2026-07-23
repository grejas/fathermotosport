<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Listado con filtros: category, brand, min_price, max_price, search, sort, per_page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 12), 60);

        $products = Product::query()
            ->active()
            ->with(['brand', 'category', 'images', 'variants'])
            ->withCount('reviews')
            ->filter($request->only(['category', 'brand', 'min_price', 'max_price', 'search', 'is_new', 'is_popular', 'is_featured']))
            ->tap(fn ($q) => $this->applySort($q, $request->input('sort')))
            ->paginate($perPage)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Detalle completo por slug.
     */
    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with([
                'brand',
                'category',
                'images',
                'variants',
                'model3d',
                'visorColors',
                'reviews' => fn ($q) => $q->approved()->with('user')->latest(),
            ])
            ->withCount(['reviews' => fn ($q) => $q->approved()])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->approved()], 'rating')
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * 6 productos destacados.
     */
    public function featured(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->active()
            ->featured()
            ->with(['brand', 'category', 'images', 'variants'])
            ->latest()
            ->take(6)
            ->get();

        return ProductResource::collection($products);
    }

    /**
     * Búsqueda por nombre, marca o descripción.
     */
    public function search(Request $request, ?string $query = null): AnonymousResourceCollection
    {
        $term = $query ?? $request->input('q', '');

        $products = Product::query()
            ->active()
            ->with(['brand', 'category', 'images', 'variants'])
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"));
            })
            ->paginate(min((int) $request->input('per_page', 12), 60))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    private function applySort($query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc' => $query->orderBy('name'),
            'newest' => $query->latest(),
            'popular' => $query->orderByDesc('is_popular')->latest(),
            default => $query->orderByDesc('is_featured')->latest(),
        };
    }
}
