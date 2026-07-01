<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Se convierte la ruta relativa de la DB en URL pública absoluta.
            'url' => ProductImage::publicUrl($this->url),
            'thumbnail_url' => ProductImage::publicUrl($this->thumbnail_url),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
