<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URL de una imagen lista para un correo, o null si es mejor no mostrarla.
 *
 * Gmail descarga las imágenes por su proxy y descarta las URLs con espacios u otros
 * caracteres sin codificar: un archivo subido como "Black Converse Pants.png" quedaba
 * como imagen rota. La URL sale absoluta, en https y codificada (ver UrlPublica).
 *
 * Y devuelve null, para no dejar una imagen rota, si el formato no lo muestran todos
 * los clientes (WebP, AVIF, SVG) o si es un archivo de nuestro /storage que no existe.
 */
class ImagenDeCorreo
{
    /** Formatos que muestran Gmail, Outlook y Apple Mail. */
    private const EXTENSIONES = ['png', 'jpg', 'jpeg', 'gif'];

    /**
     * @param  bool  $comprobarArchivo  Si la URL es de nuestro /storage, exige que el
     *                                  archivo exista en el disco public.
     */
    public static function url(?string $url, bool $comprobarArchivo = false): ?string
    {
        $url = UrlPublica::absoluta($url);

        if ($url === null) {
            return null;
        }

        $partes = parse_url($url);

        if ($partes === false || empty($partes['scheme'])) {
            return null;
        }

        $ruta = $partes['path'] ?? '';
        $extension = strtolower(pathinfo(rawurldecode($ruta), PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONES, true)) {
            return null;
        }

        if ($comprobarArchivo && ($relativa = self::rutaEnStorage($url)) !== null
            && ! Storage::disk('public')->exists($relativa)) {
            return null;
        }

        return UrlPublica::de($url);
    }

    /**
     * Ruta dentro del disco public si la URL apunta a nuestro /storage (sea http o
     * https, codificada o no). Null si es de otro lado (R2, un CDN): esa no se puede
     * comprobar sin pedirla, y una petición por correo no vale la pena.
     */
    private static function rutaEnStorage(string $url): ?string
    {
        $base = parse_url((string) config('filesystems.disks.public.url'));
        $imagen = parse_url($url);

        if (empty($base['host']) || strtolower($base['host']) !== strtolower($imagen['host'] ?? '')) {
            return null;
        }

        $prefijo = rtrim($base['path'] ?? '', '/').'/';
        $ruta = rawurldecode($imagen['path'] ?? '');

        return str_starts_with($ruta, $prefijo) ? substr($ruta, strlen($prefijo)) : null;
    }
}
