<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            // Solo llega a quien ya está autorizado a ver el pedido; es lo que permite
            // al comprador invitado volver a consultarlo sin cuenta.
            'access_token' => $this->access_token,
            'user_id' => $this->user_id,
            'guest_email' => $this->guest_email,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'shipping' => $this->shipping,
            'shipping_method_name' => $this->shipping_method_name,
            'tax' => $this->tax,
            'total' => $this->total,
            'payment_status' => $this->payment_status,
            'shipping_status' => $this->shipping_status,
            'payment_method' => $this->payment_method,
            'country' => $this->country,
            'notes' => $this->notes,

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'address' => new AddressResource($this->whenLoaded('address')),
            'payment' => new PaymentResource($this->whenLoaded('payments', fn () => $this->payments->last())),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'tracking_number' => $this->shipment->tracking_number,
                'carrier' => $this->shipment->carrier,
                'status' => $this->shipment->status,
                'shipped_at' => $this->shipment->shipped_at,
                'delivered_at' => $this->shipment->delivered_at,
            ] : null),

            'created_at' => $this->created_at,
        ];
    }
}
