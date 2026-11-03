<?php

namespace App\Http\Resources;

use App\Models\ReviewImage;
use App\Support\UrlPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'is_approved' => $this->is_approved,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->full_name,
                'avatar' => $this->user->avatar,
                'country' => $this->user->country,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
            // Solo las aprobadas (relación approvedImages); las pendientes no tienen URL pública.
            'images' => $this->whenLoaded('approvedImages', fn () => $this->approvedImages->map(fn (ReviewImage $foto) => [
                'id' => $foto->id,
                'url' => UrlPublica::deStorage($foto->path),
                'thumb_url' => UrlPublica::deStorage($foto->thumb_path),
                'width' => $foto->width,
                'height' => $foto->height,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
