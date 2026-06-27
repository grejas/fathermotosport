<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Guest checkout: el email es obligatorio solo si no hay usuario autenticado.
            'guest_email' => [
                $this->user() ? 'nullable' : 'required',
                'email',
                'max:255',
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'address' => ['required', 'array'],
            'address.full_name' => ['required', 'string', 'max:255'],
            'address.phone' => ['required', 'string', 'max:20'],
            'address.country' => ['required', 'in:Bolivia,Brasil'],
            'address.state' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.address_line' => ['required', 'string', 'max:255'],
            'address.reference' => ['nullable', 'string', 'max:255'],

            'payment_method' => ['required', 'in:paypal,stripe,mercadopago'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
