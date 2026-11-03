<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // Idioma de la web al iniciar sesión: se guarda como idioma del cliente.
            'locale' => ['nullable', 'string', 'in:es,pt,en'],
        ];
    }
}
