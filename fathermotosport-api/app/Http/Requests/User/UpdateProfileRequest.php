<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\DatosPersonales;
use App\Rules\TelefonoInternacional;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            // Mismas reglas que el registro (DatosPersonales): con código de país, y una
            // fecha no futura de alguien de 18 años o más.
            'phone' => ['nullable', 'string', 'max:25', new TelefonoInternacional],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1926-01-01', DatosPersonales::reglaFechaDeNacimiento()],
            'avatar' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return DatosPersonales::mensajes();
    }
}
