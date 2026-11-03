<?php

namespace App\Support;

/**
 * Primer nombre para saludar en un correo ("gerson rejas" → "Gerson"), o null si no hay un
 * nombre utilizable y conviene saludar sin nombre.
 *
 * Toma la primera palabra, le quita la puntuación de los bordes y la pone con la primera
 * letra en mayúscula (también tras un guion o apóstrofo: "ana-maría" → "Ana-María").
 * No sirve (null): vacío, un correo, con números u otros símbolos, menos de 2 letras, más
 * de 30 caracteres o un genérico como "Cliente".
 */
class PrimerNombre
{
    private const GENERICOS = ['cliente', 'client', 'customer', 'usuario', 'user', 'rider', 'test', 'prueba', 'null', 'none', 'nombre', 'name'];

    public static function de(?string $texto): ?string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $texto));
        if ($texto === '' || str_contains($texto, '@')) {
            return null;
        }

        $palabra = explode(' ', $texto)[0];
        // Fuera la puntuación y los símbolos de los bordes ("¡Gerson!", "(ana)", "gerson,").
        $palabra = (string) preg_replace('/^[^\p{L}]+|[^\p{L}\p{M}]+$/u', '', $palabra);

        if ($palabra === ''
            || mb_strlen($palabra) > 30
            || ! preg_match('/^[\p{L}\p{M}]+(?:[\'’-][\p{L}\p{M}]+)*$/u', $palabra)
            || preg_match_all('/\p{L}/u', $palabra) < 2
            || in_array(mb_strtolower($palabra), self::GENERICOS, true)) {
            return null;
        }

        $palabra = mb_convert_case(mb_strtolower($palabra), MB_CASE_TITLE, 'UTF-8');

        // MB_CASE_TITLE no sube la letra que sigue a un apóstrofo: "O'brien" → "O'Brien".
        return (string) preg_replace_callback('/([\'’])(\p{Ll})/u', fn ($m) => $m[1].mb_strtoupper($m[2]), $palabra);
    }
}
