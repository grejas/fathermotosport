<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear una cuenta a partir de un pedido de invitado ya pagado.
 * El nombre, el email y el teléfono salen del propio pedido: acá solo se pide
 * la contraseña. La autorización la da el token del pedido (X-Order-Token),
 * que se valida en el controlador.
 */
class RegisterFromOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            // Mismas reglas que el registro normal.
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo (cualquier carácter especial).',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
