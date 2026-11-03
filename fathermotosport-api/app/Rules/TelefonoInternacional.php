<?php

namespace App\Rules;

use App\Support\Paises;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Teléfono con código de país: + y 8 a 15 dígitos, con un prefijo de país existente. */
class TelefonoInternacional implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Paises::telefonoInternacional(is_string($value) ? $value : null) === null) {
            $fail(__('registro.phone_invalid'));
        }
    }
}
