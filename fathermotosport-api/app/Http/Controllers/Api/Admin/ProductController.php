<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Crea un producto (solo admin/empleado).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Producto creado.',
            'product' => new ProductResource($product->load(['brand', 'category'])),
        ], 201);
    }

    /**
     * Actualiza un producto.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado.',
            'product' => new ProductResource($product->fresh(['brand', 'category', 'images', 'variants'])),
        ]);
    }

    /**
     * Elimina (soft delete) un producto.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado.']);
    }
}
