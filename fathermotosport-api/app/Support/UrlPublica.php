<?php

namespace App\Support;

/**
 * URL pública lista para usar fuera de la app (correos, la web, un <video>):
 *   - absoluta (una ruta relativa se completa con APP_URL),
 *   - en https (salvo en un host de desarrollo local, que no lo tiene),
 *   - con cada segmento de la ruta codificado una sola vez: se decodifica antes de
 *     codificar, así "%20" no termina en "%2520".
 *
 * Es la lógica que nació en ImagenDeCorreo (Gmail descartaba las URLs con espacios);
 * ImagenDeCorreo le suma solo sus reglas de formato y de existencia del archivo.
 */
class UrlPublica
{
    /** Hosts de desarrollo: sin certificado, así que no se pasan a https. */
    private const HOSTS_LOCALES = ['localhost', '127.0.0.1', '::1'];

    public static function de(?string $url): ?string
    {
        $url = self::absoluta($url);

        if ($url === null) {
            return null;
        }

        $partes = parse_url($url);

        if ($partes === false || empty($partes['scheme'])) {
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
            explode('/', $partes['path'] ?? '')
        ));

        $autoridad = $host === '' ? '' : '//'.$host.(isset($partes['port']) ? ':'.$partes['port'] : '');

        return $esquema.':'.$autoridad.$rutaCodificada
            .(isset($partes['query']) ? '?'.$partes['query'] : '');
    }

    /**
     * Archivo del disco public (ruta relativa como "banners/x.mp4", que es lo que
     * guarda Filament) o una URL ya absoluta, como URL pública codificada.
     */
    public static function deStorage(?string $ruta): ?string
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $ruta)) {
            return self::de($ruta);
        }

        return self::de(rtrim((string) config('filesystems.disks.public.url'), '/').'/'.ltrim($ruta, '/'));
    }

    /** La URL completada con APP_URL si es relativa; null si está vacía. */
    public static function absoluta(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return $url;
    }

    private static function esHostLocal(string $host): bool
    {
        return in_array(trim($host, '[]'), self::HOSTS_LOCALES, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }
}
