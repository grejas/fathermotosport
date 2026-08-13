<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Igual criterio que el frontend: solo +, dígitos, espacios, guiones y
 * paréntesis, y entre 7 y 15 dígitos reales (sin contar esos símbolos).
 */
class ValidPhone implements ValidationRule
{
    private const MESSAGE = 'Ingresá un número de teléfono válido con al menos 7 dígitos';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (! preg_match('/^[+\d\s\-()]+$/', $value)) {
            $fail(self::MESSAGE);

            return;
        }

        $digitCount = strlen(preg_replace('/\D/', '', $value));

        if ($digitCount < 7 || $digitCount > 15) {
            $fail(self::MESSAGE);
        }
    }
}
