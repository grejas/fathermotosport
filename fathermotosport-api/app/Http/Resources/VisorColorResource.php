<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisorColorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hex_color' => $this->hex_color,
            'image_url' => ProductImage::publicUrl($this->image_url),
            'sort_order' => $this->sort_order,
        ];
    }
}
