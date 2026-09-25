<?php

namespace App\Support;

/**
 * Destinos a los que se ofrece envío: lista fija y cerrada de 40 países
 * (20 de América y 20 de Europa), definida a mano.
 *
 * Es la misma lista que usa el checkout (lib/data/shippingCountries.ts) y la única
 * que ve el admin al cargar una opción de envío. El registro de usuarios usa
 * Countries (los ~249 del mundo), porque cualquiera puede crear una cuenta.
 */
class ShippingCountries
{
    /** @var array<string, string> código ISO => nombre */
    public const AMERICAS = [
        'BO' => 'Bolivia',
        'AR' => 'Argentina',
        'BR' => 'Brasil',
        'CL' => 'Chile',
        'PE' => 'Perú',
        'PY' => 'Paraguay',
        'UY' => 'Uruguay',
        'EC' => 'Ecuador',
        'CO' => 'Colombia',
        'VE' => 'Venezuela',
        'MX' => 'México',
        'US' => 'Estados Unidos',
        'CA' => 'Canadá',
        'CR' => 'Costa Rica',
        'PA' => 'Panamá',
        'GT' => 'Guatemala',
        'SV' => 'El Salvador',
        'HN' => 'Honduras',
        'NI' => 'Nicaragua',
        'DO' => 'República Dominicana',
    ];

    /** @var array<string, string> código ISO => nombre */
    public const EUROPE = [
        'ES' => 'España',
        'PT' => 'Portugal',
        'FR' => 'Francia',
        'IT' => 'Italia',
        'DE' => 'Alemania',
        'GB' => 'Reino Unido',
        'BE' => 'Bélgica',
        'NL' => 'Países Bajos',
        'CH' => 'Suiza',
        'AT' => 'Austria',
        'SE' => 'Suecia',
        'NO' => 'Noruega',
        'DK' => 'Dinamarca',
        'FI' => 'Finlandia',
        'IE' => 'Irlanda',
        'PL' => 'Polonia',
        'CZ' => 'República Checa',
        'HU' => 'Hungría',
        'RO' => 'Rumania',
        'GR' => 'Grecia',
    ];

    /**
     * Los 40 destinos ordenados por nombre, listos para un Select de Filament.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $all = self::AMERICAS + self::EUROPE;
        asort($all, SORT_NATURAL | SORT_FLAG_CASE);

        return $all;
    }

    public static function name(?string $code): ?string
    {
        return (self::AMERICAS + self::EUROPE)[strtoupper((string) $code)] ?? null;
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && self::name($code) !== null;
    }
}
