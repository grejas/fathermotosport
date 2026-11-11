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
            // Color al que pertenece la foto; null = sirve para todos los colores.
            'color' => $this->color,
            // Se convierte la ruta relativa de la DB en URL pública absoluta.
            'url' => ProductImage::publicUrl($this->url),
            // image, gif o video. thumbnail_url: en un GIF, su primer fotograma estático.
            'type' => $this->media_type ?? ProductImage::IMAGEN,
            'thumbnail_url' => ProductImage::publicUrl($this->thumbnail_url),
            'poster_url' => ProductImage::publicUrl($this->poster_url),
            'with_sound' => (bool) $this->with_sound,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
