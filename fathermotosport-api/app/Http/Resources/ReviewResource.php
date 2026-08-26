<?php

namespace App\Http\Resources;

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
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
