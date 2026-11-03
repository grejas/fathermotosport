<?php

namespace App\Rules;

use App\Services\ResolverMx;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Comprobaciones gratuitas, sin llamar a Resend: el dominio no es de correo desechable
 * conocido (config marketing.disposable_domains) y tiene registro MX.
 *
 * No prueba que el buzón exista: solo la verificación por código lo hace.
 */
class CorreoEntregable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dominio = strtolower(trim((string) substr(strrchr((string) $value, '@') ?: '', 1)));
        if ($dominio === '') {
            return; // El formato lo valida la regla email.
        }

        foreach (config('marketing.disposable_domains', []) as $desechable) {
            if ($dominio === $desechable || str_ends_with($dominio, '.'.$desechable)) {
                $fail(__('registro.email_disposable'));

                return;
            }
        }

        if (! app(ResolverMx::class)->tieneMx($dominio)) {
            $fail(__('registro.email_no_mx'));
        }
    }
}
