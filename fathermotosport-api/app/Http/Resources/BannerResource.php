<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use App\Support\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $esVideo = $this->media_type === 'video';

        return [
            'id' => $this->id,
            // Opcionales: solo los usa "Debajo de categorías" (el hero tiene textos fijos).
            'title' => filled($this->title) ? $this->title : null,
            'subtitle' => filled($this->subtitle) ? $this->subtitle : null,
            // La DB guarda la ruta relativa (banners/xxx.png); el frontend
            // necesita la URL pública absoluta. Se reutiliza el mismo helper
            // que las imágenes de producto para mantener una sola convención.
            'image_url' => ProductImage::publicUrl($this->image_url),
            'link_url' => $this->link_url,
            'button_text' => $this->button_text,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            // Video subido desde el panel. URLs absolutas, en https y codificadas: van
            // directo a un <video>, y un nombre con espacios no puede romperlo.
            'media_type' => $esVideo ? 'video' : 'image',
            'video_url' => $esVideo ? UrlPublica::deStorage($this->video_path) : null,
            'video_mobile_url' => $esVideo ? UrlPublica::deStorage($this->video_mobile_path) : null,
            'poster_url' => $esVideo ? UrlPublica::deStorage($this->poster_path) : null,
            'video_with_sound' => $esVideo && (bool) $this->video_with_sound,
        ];
    }
}
