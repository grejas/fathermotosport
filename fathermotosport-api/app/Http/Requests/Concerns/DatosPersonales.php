<?php

namespace App\Http\Requests\Concerns;

use App\Rules\TelefonoInternacional;
use App\Support\Paises;
use App\Support\PaisesDatos;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Teléfono, país, fecha de nacimiento y declaración de mayoría de edad: mismas reglas en
 * el registro y en "Completa tus datos".
 *
 * - Teléfono con código de país (+59176543210) y país ISO: obligatorios.
 * - Fecha de nacimiento opcional: no futura (fecha de La Paz) y 18 años o más (cumplirlos
 *   hoy cuenta). Si no hay fecha, la casilla "Tengo 18 años o más" es obligatoria.
 */
final class DatosPersonales
{
    public static function reglas(): array
    {
        return [
            'phone' => ['required', 'string', 'max:25', new TelefonoInternacional],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(PaisesDatos::PAISES))],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1926-01-01', self::reglaFechaDeNacimiento()],
            // Solo cuenta si no hay fecha: con fecha, se ignora.
            'adult_confirmed' => ['exclude_unless:birth_date,null', 'required', 'accepted'],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'phone.required' => __('registro.phone_required'),
            'phone.max' => __('registro.phone_invalid'),
            'country.required' => __('registro.country_required'),
            'country.size' => __('registro.country_invalid'),
            'country.in' => __('registro.country_invalid'),
            'birth_date.date_format' => __('registro.birth_date_invalid'),
            'birth_date.after_or_equal' => __('registro.birth_date_invalid'),
            'adult_confirmed.required' => __('registro.adult_required'),
            'adult_confirmed.accepted' => __('registro.adult_required'),
        ];
    }

    /** Hoy en la zona horaria de la tienda (La Paz), para "no futura" y la edad. */
    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(config('marketing.timezone', 'America/La_Paz'))->startOfDay();
    }

    public static function edad(string $fecha): int
    {
        return (int) CarbonImmutable::createFromFormat('!Y-m-d', $fecha, config('marketing.timezone', 'America/La_Paz'))
            ->diffInYears(self::hoy());
    }

    /**
     * Datos listos para guardar: teléfono normalizado, país en mayúsculas y qué se
     * declaró sobre la edad y cuándo (birth_date = lo prueba la fecha; declared = casilla).
     */
    public static function paraGuardar(array $datos): array
    {
        $fecha = $datos['birth_date'] ?? null;

        return [
            'phone' => Paises::telefonoInternacional($datos['phone'] ?? null),
            'country' => strtoupper((string) $datos['country']),
            'birth_date' => $fecha ?: null,
            'adult_confirmation' => $fecha ? 'birth_date' : 'declared',
            'adult_confirmed_at' => now(),
        ];
    }

    public static function reglaFechaDeNacimiento(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
                || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return; // date_format ya da el error.
            }

            if ($value > self::hoy()->toDateString()) {
                $fail(__('registro.birth_date_future'));

                return;
            }

            if (self::edad($value) < 18) {
                $fail(__('registro.underage'));
            }
        };
    }
}
