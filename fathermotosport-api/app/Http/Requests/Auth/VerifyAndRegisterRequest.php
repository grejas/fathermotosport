<?php

namespace App\Http\Requests\Auth;

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
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'code' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'last_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'birth_date' => ['nullable', 'date', 'after:1925-12-31', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', 'regex:/^[\+\d\s\-\(\)]{7,20}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Ingresa un email válido (verificá que el dominio exista).',
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
            'phone.regex' => 'Ingresá un número de teléfono válido con al menos 7 dígitos',
            'phone.min' => 'Ingresá un número de teléfono válido con al menos 7 dígitos',
            'phone.max' => 'Ingresá un número de teléfono válido con al menos 7 dígitos',
            'password.regex' => 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo (cualquier carácter especial).',
        ];
    }
}
