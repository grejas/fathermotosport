<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store_name' => $this->store_name,
            // Rutas relativas en DB (store/xxx.png) → URL pública absoluta.
            'logo_url' => ProductImage::publicUrl($this->logo_url),
            'favicon_url' => ProductImage::publicUrl($this->favicon_url),
            'phone' => $this->phone,
            'email' => $this->email,
            'currency' => $this->currency ?? 'USD',
            'whatsapp' => $this->whatsapp,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'youtube' => $this->youtube,
            'maintenance_mode' => (bool) $this->maintenance_mode,
            // OJO: payment_keys NO se expone — contiene secretos de las pasarelas.
        ];
    }
}
