<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido "PayPal Express": se crea desde el carrito con lo mínimo indispensable.
 * El nombre, el email y la dirección los completa PayPal al volver de la aprobación.
 * El checkout normal sigue usando StoreOrderRequest, que exige todos los datos.
 */
class StoreExpressOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            // El país lo elige el cliente en el carrito: es lo único que hace falta
            // para cobrar el envío correcto antes de mandarlo a PayPal.
            'shipping_country_code' => ['required', 'string', 'size:2'],
            'shipping_option_id' => ['required', 'integer', 'exists:shipping_options,id'],
            // Adónde avisarle del pedido. Obligatorio aunque PayPal informe otro al
            // pagar: sin él, un Express abandonado no tendría a quién escribirle.
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['nullable', 'string', 'max:5'],
        ];
    }
}
