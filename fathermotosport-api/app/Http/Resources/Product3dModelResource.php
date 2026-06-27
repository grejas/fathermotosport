<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Product3dModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_glb_url' => $this->file_glb_url,
            'file_draco_url' => $this->file_draco_url,
            'preview_url' => $this->preview_url,
            'file_size_kb' => $this->file_size_kb,
            'version' => $this->version,
        ];
    }
}
