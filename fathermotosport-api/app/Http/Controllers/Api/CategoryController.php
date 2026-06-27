<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Árbol completo de categorías raíz con sus hijos.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->active()
            ->root()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Categoría con sus productos paginados.
     */
    public function show(Request $request, string $slug): array
    {
        $category = Category::where('slug', $slug)->active()->firstOrFail();

        $products = $category->products()
            ->active()
            ->with(['brand', 'category', 'images', 'variants'])
            ->paginate(min((int) $request->input('per_page', 12), 60))
            ->withQueryString();

        return [
            'data' => new CategoryResource($category),
            'products' => ProductResource::collection($products)->response()->getData(true),
        ];
    }
}
