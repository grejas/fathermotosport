<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'recaptcha_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Ingresa un email válido (verificá que el dominio exista).',
        ];
    }
}
