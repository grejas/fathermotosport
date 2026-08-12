<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'last_name' => ['required', 'string', 'max:25', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', 'regex:/^[\+\d\s\-\(\)]+$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'],
            'recaptcha_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.regex' => 'El nombre solo puede contener letras (máx. 25 caracteres).',
            'first_name.max' => 'El nombre solo puede contener letras (máx. 25 caracteres).',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras (máx. 25 caracteres).',
            'last_name.max' => 'El apellido solo puede contener letras (máx. 25 caracteres).',
            'phone.regex' => 'Ingresa un número válido con código de país (ej: +591 68736384).',
            'phone.min' => 'Ingresa un número válido con código de país (ej: +591 68736384).',
            'phone.max' => 'Ingresa un número válido con código de país (ej: +591 68736384).',
        ];
    }
}
