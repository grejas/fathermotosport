<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
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
            // Una cuenta de cliente sin verificar no bloquea: quien verifique el código
            // la reclama (ver User::reclamablePorEmail).
            'email' => ['required', 'email:rfc,dns', 'max:255', User::reglaEmailOcupado()],
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
