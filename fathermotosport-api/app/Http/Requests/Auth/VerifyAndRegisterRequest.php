<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\DatosPersonales;
use App\Models\User;
use App\Rules\CorreoEntregable;
use Illuminate\Foundation\Http\FormRequest;

class VerifyAndRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Una cuenta de cliente sin verificar no bloquea: quien verifique el código
            // la reclama (ver User::reclamablePorEmail).
            'email' => ['required', 'email:rfc,dns', 'max:255', User::reglaEmailOcupado(), new CorreoEntregable],
            'code' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'last_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            ...DatosPersonales::reglas(),
            'locale' => ['nullable', 'string', 'in:es,pt,en'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            ...DatosPersonales::mensajes(),
            'email.email' => 'Ingresa un email válido (verificá que el dominio exista).',
            'code.required' => 'Ingresa el código de verificación.',
            'code.digits' => 'El código debe tener 6 dígitos.',
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.regex' => 'El nombre solo puede contener letras (máx. 25 caracteres).',
            'first_name.max' => 'El nombre solo puede contener letras (máx. 25 caracteres).',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras (máx. 25 caracteres).',
            'last_name.max' => 'El apellido solo puede contener letras (máx. 25 caracteres).',
            'password.regex' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo (cualquier carácter especial).',
        ];
    }
}
