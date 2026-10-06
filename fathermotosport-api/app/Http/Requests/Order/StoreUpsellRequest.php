<?php

namespace App\Http\Requests\Order;

use App\Services\UpsellService;
use Illuminate\Foundation\Http\FormRequest;

class StoreUpsellRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso se resuelve en el controlador con el token del pedido.
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.UpsellService::MAX_OFERTAS],
            'items.*.rule_id' => ['required', 'integer'],
            'items.*.variant_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Elegí al menos un producto.',
            'items.min' => 'Elegí al menos un producto.',
            'items.max' => 'No se pueden agregar más de :max productos.',
        ];
    }
}
