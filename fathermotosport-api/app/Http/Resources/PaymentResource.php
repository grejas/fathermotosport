<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'transaction_id' => $this->transaction_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
        ];
    }
}
