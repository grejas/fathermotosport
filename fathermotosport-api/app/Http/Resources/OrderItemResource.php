<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'size' => $this->size,
            'color' => $this->color,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
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
