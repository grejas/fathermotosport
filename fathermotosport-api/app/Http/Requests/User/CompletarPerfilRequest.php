<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\DatosPersonales;
use Illuminate\Foundation\Http\FormRequest;

/** Ventana "Completa tus datos" (clientes antiguos o de Google): mismas reglas que el registro. */
class CompletarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return DatosPersonales::reglas();
    }

    public function messages(): array
    {
        return DatosPersonales::mensajes();
    }
}
