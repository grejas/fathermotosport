<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'color' => $this->variant->color,
                'size' => $this->variant->size,
                'stock' => $this->variant->stock,
                'in_stock' => $this->variant->stock > 0,
                'product' => $this->when(
                    $this->variant->relationLoaded('product') && $this->variant->product,
                    fn () => [
                        'id' => $this->variant->product->id,
                        'name' => $this->variant->product->name,
                        'slug' => $this->variant->product->slug,
                        'primary_image' => $this->variant->product->primary_image,
                    ]
                ),
            ]),
        ];
    }
}
