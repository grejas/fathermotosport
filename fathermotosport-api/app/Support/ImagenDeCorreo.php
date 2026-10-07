<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URL de una imagen lista para un correo, o null si es mejor no mostrarla.
 *
 * Gmail descarga las imágenes por su proxy y descarta las URLs con espacios u otros
 * caracteres sin codificar: un archivo subido como "Black Converse Pants.png" quedaba
 * como imagen rota. Acá la URL sale:
 *   - absoluta (una ruta relativa se completa con APP_URL),
 *   - en https (salvo en un host de desarrollo local, que no lo tiene),
 *   - con cada segmento de la ruta codificado una sola vez: se decodifica antes de
 *     codificar, así "%20" no termina en "%2520".
 *
 * Y devuelve null, para no dejar una imagen rota, si el formato no lo muestran todos
 * los clientes (WebP, AVIF, SVG) o si es un archivo de nuestro /storage que no existe.
 */
class ImagenDeCorreo
{
    /** Formatos que muestran Gmail, Outlook y Apple Mail. */
    private const EXTENSIONES = ['png', 'jpg', 'jpeg', 'gif'];

    /** Hosts de desarrollo: sin certificado, así que no se pasan a https. */
    private const HOSTS_LOCALES = ['localhost', '127.0.0.1', '::1'];

    /**
     * @param  bool  $comprobarArchivo  Si la URL es de nuestro /storage, exige que el
     *                                  archivo exista en el disco public.
     */
    public static function url(?string $url, bool $comprobarArchivo = false): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
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

        $esquema = strtolower($partes['scheme']);
        $host = strtolower($partes['host'] ?? '');

        // Solo las vistas previas locales usan file:// (el segmento "C:" no se puede
        // codificar sin romper la ruta): basta con los espacios.
        if ($esquema === 'file') {
            return str_replace(' ', '%20', $url);
        }

        if ($esquema === 'http' && ! self::esHostLocal($host)) {
            $esquema = 'https';
        }

        // Cada segmento por separado: las barras quedan como separadores.
        $rutaCodificada = implode('/', array_map(
            fn (string $segmento) => rawurlencode(rawurldecode($segmento)),
            explode('/', $ruta)
        ));

        $autoridad = $host === '' ? '' : '//'.$host.(isset($partes['port']) ? ':'.$partes['port'] : '');

        return $esquema.':'.$autoridad.$rutaCodificada
            .(isset($partes['query']) ? '?'.$partes['query'] : '');
    }

    private static function esHostLocal(string $host): bool
    {
        return in_array(trim($host, '[]'), self::HOSTS_LOCALES, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
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
