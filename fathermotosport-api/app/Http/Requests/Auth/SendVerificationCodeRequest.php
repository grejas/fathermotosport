<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\DatosPersonales;
use App\Models\User;
use App\Rules\CorreoEntregable;
use Illuminate\Foundation\Http\FormRequest;

class SendVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Teléfono, país, fecha y casilla de edad se validan acá solo si la web los manda
        // (sometimes), para avisar antes de enviar el código. Son obligatorios al crear
        // la cuenta (VerifyAndRegisterRequest).
        $datos = array_map(fn (array $reglas) => ['sometimes', ...$reglas], DatosPersonales::reglas());
        $datos['adult_confirmed'] = ['exclude_unless:birth_date,null', 'sometimes', 'accepted'];

        return [
            // Una cuenta de cliente sin verificar no bloquea: quien verifique el código
            // la reclama (ver User::reclamablePorEmail).
            'email' => ['required', 'email:rfc,dns', 'max:255', User::reglaEmailOcupado(), new CorreoEntregable],
            'recaptcha_token' => ['required', 'string'],
            ...$datos,
        ];
    }

    public function messages(): array
    {
        return [
            ...DatosPersonales::mensajes(),
            'email.email' => 'Ingresa un email válido (verificá que el dominio exista).',
        ];
    }
}
