<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // La DB guarda la ruta relativa (banners/xxx.png); el frontend
            // necesita la URL pública absoluta. Se reutiliza el mismo helper
            // que las imágenes de producto para mantener una sola convención.
            'image_url' => ProductImage::publicUrl($this->image_url),
            'link_url' => $this->link_url,
            'button_text' => $this->button_text,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
