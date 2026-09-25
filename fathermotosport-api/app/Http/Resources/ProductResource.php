<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'color' => $this->color,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'cost' => $this->when($this->isAdminRequest($request), $this->cost),
            'weight' => $this->weight,
            'minimum_stock' => $this->minimum_stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_new' => $this->is_new,
            'is_popular' => $this->is_popular,
            'specs' => $this->specs,
            'certification' => $this->certification,
            'spin_url' => $this->spin_url,

            // Campos calculados
            'discount_percent' => $this->discount_percent,
            'primary_image' => $this->primary_image,
            'has_3d_model' => $this->has_3d_model,
            'in_stock' => $this->resolveInStock(),
            'total_stock' => $this->whenLoaded('variants', fn () => (int) $this->variants->where('is_active', true)->sum('stock')),

            // Relaciones anidadas
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon,
            ]),
            'images' => ProductImageResource::collection(
                $this->whenLoaded('images', fn () => $this->images
                    ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
                    ->values())
            ),
            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants', fn () => $this->variants
                    ->where('is_active', true)
                    ->values())
            ),
            'model_3d' => new Product3dModelResource($this->whenLoaded('model3d')),
            'visor_colors' => VisorColorResource::collection($this->whenLoaded('visorColors')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'reviews_count' => $this->whenCounted('reviews'),
            'rating_avg' => $this->when(isset($this->reviews_avg_rating), fn () => round((float) $this->reviews_avg_rating, 1)),

            'created_at' => $this->created_at,
        ];
    }

    private function resolveInStock(): bool
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->where('is_active', true)->where('stock', '>', 0)->isNotEmpty();
        }

        return $this->variants()->where('is_active', true)->where('stock', '>', 0)->exists();
    }

    private function isAdminRequest(Request $request): bool
    {
        $user = $request->user();

        return $user && method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEmpleado());
    }
}
