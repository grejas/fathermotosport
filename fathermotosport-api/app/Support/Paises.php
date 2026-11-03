<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Países del registro de clientes: código ISO, prefijo telefónico y nombre en es/pt/en.
 * Datos en PaisesDatos (generado).
 */
final class Paises
{
    /** @var array<string, string>|null nombre normalizado => ISO */
    private static ?array $porNombre = null;

    public static function existe(?string $iso): bool
    {
        return is_string($iso) && isset(PaisesDatos::PAISES[strtoupper($iso)]);
    }

    /**
     * Código ISO de un país escrito como código ("bo") o como nombre en es/pt/en
     * ("Bolivia", "Bolívia", "Estados Unidos", "United States"). null si no se reconoce.
     */
    public static function iso(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (strlen($valor) === 2 && self::existe($valor)) {
            return strtoupper($valor);
        }

        return self::indice()[self::normalizar($valor)] ?? null;
    }

    /** Prefijos telefónicos de un país, solo dígitos (ej. BO => ["591"]). */
    public static function prefijos(?string $iso): array
    {
        return self::existe($iso) ? PaisesDatos::PAISES[strtoupper($iso)][0] : [];
    }

    /**
     * "+591 7654-3210" => "+59176543210". null si no tiene el formato internacional
     * (+ seguido de 8 a 15 dígitos) o si el prefijo no es de ningún país.
     */
    public static function telefonoInternacional(?string $telefono): ?string
    {
        $telefono = trim((string) $telefono);
        if (! preg_match('/^\+[\d\s\-().]+$/', $telefono)) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $telefono);
        if (! preg_match('/^[1-9]\d{7,14}$/', $digitos)) {
            return null;
        }

        foreach (PaisesDatos::PAISES as [$prefijos]) {
            foreach ($prefijos as $prefijo) {
                if (str_starts_with($digitos, $prefijo)) {
                    return '+'.$digitos;
                }
            }
        }

        return null;
    }

    /**
     * Teléfono de una dirección de envío para guardarlo en la cuenta: si ya viene en
     * formato internacional se usa tal cual; si es local, se le antepone el prefijo del
     * país. null si no queda un número válido.
     */
    public static function telefonoDesdeDireccion(?string $telefono, ?string $iso): ?string
    {
        if ($internacional = self::telefonoInternacional($telefono)) {
            return $internacional;
        }

        $digitos = ltrim(preg_replace('/\D/', '', (string) $telefono), '0');
        $prefijo = self::prefijos($iso)[0] ?? null;

        return $digitos !== '' && $prefijo !== null ? self::telefonoInternacional('+'.$prefijo.$digitos) : null;
    }

    private static function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($texto)));
    }

    /** @return array<string, string> */
    private static function indice(): array
    {
        if (self::$porNombre === null) {
            self::$porNombre = [];
            foreach (PaisesDatos::PAISES as $iso => [, $nombres]) {
                foreach ($nombres as $nombre) {
                    self::$porNombre[self::normalizar($nombre)] ??= $iso;
                }
            }
        }

        return self::$porNombre;
    }
}
