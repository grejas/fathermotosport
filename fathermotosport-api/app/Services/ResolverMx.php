<?php

namespace App\Services;

/**
 * ¿El dominio tiene registro MX? Aparte para poder sustituirlo en los tests (sin red).
 *
 * Que haya MX solo dice que el dominio recibe correo, no que el buzón exista: eso lo
 * prueba únicamente el código de verificación.
 */
class ResolverMx
{
    public function tieneMx(string $dominio): bool
    {
        $dominio = rtrim(strtolower(trim($dominio)), '.');

        return $dominio !== '' && checkdnsrr($dominio.'.', 'MX');
    }
}
