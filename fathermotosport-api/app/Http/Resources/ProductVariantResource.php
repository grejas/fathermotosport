<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'color' => $this->color,
            'size' => $this->size,
            'finish' => $this->finish,
            'sku' => $this->sku,
            'stock' => (int) $this->stock,
            'price' => $this->price,
            'in_stock' => $this->stock > 0,
            'is_active' => $this->is_active,
        ];
    }
}
