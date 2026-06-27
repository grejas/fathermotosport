<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');
        $subtotal = $this->relationLoaded('items')
            ? $this->items->sum(fn ($item) => (float) $item->price * $item->quantity)
            : 0;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'expires_at' => $this->expires_at,
            'items' => CartItemResource::collection($items),
            'items_count' => $this->relationLoaded('items') ? $this->items->sum('quantity') : 0,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'shipping' => '0.00',
            'total' => number_format($subtotal, 2, '.', ''),
        ];
    }
}
