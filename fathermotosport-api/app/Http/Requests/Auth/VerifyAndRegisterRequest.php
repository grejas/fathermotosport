<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidPhone;
use App\Models\User;
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
            'email' => ['required', 'email:rfc,dns', 'max:255', User::reglaEmailOcupado()],
            'code' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'last_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'birth_date' => ['nullable', 'date', 'after:1925-12-31', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', new ValidPhone],
            'country' => ['required', 'string', 'size:2'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Ingresa un email válido (verificá que el dominio exista).',
            'country.required' => 'El país es obligatorio.',
            'country.size' => 'Selecciona un país válido.',
            'birth_date.after' => 'La fecha debe estar entre 1926 y hoy.',
            'birth_date.before_or_equal' => 'La fecha debe estar entre 1926 y hoy.',
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
