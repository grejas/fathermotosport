<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Versiones livianas de las imágenes del storage público: WebP, al ancho que pide la web.
 * Se generan una vez y quedan como archivos estáticos en storage/app/public/v/{ancho}/.
 */
class VariantesDeImagen
{
    /** Anchos permitidos (los que usa el loader de la web). Otros dan 404: nadie llena el disco. */
    public const ANCHOS = [320, 640, 960, 1280, 1920];

    private const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

    private const CALIDAD = 76;

    /**
     * Ruta absoluta de la variante (generándola si hace falta), o null si el pedido no es
     * válido o la imagen original no existe.
     *
     * @param  string  $ruta  Ruta original dentro del storage público + ".webp" (p. ej. products/casco.jpg.webp).
     */
    public function generar(int $ancho, string $ruta): ?string
    {
        if (! in_array($ancho, self::ANCHOS, true) || ! str_ends_with($ruta, '.webp')) {
            return null;
        }

        $original = substr($ruta, 0, -strlen('.webp'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        // Nada de "..", rutas absolutas ni otras variantes: solo archivos de imagen del storage.
        if (! in_array($extension, self::EXTENSIONES, true) || str_contains($original, '..')
            || str_starts_with($original, '/') || str_starts_with($original, 'v/')) {
            return null;
        }

        $base = storage_path('app/public');
        $fuente = realpath($base.'/'.$original);

        if ($fuente === false || ! str_starts_with($fuente, realpath($base).DIRECTORY_SEPARATOR) || ! is_file($fuente)) {
            return null;
        }

        $destino = $base.'/v/'.$ancho.'/'.$ruta;

        if (is_file($destino) && filemtime($destino) >= filemtime($fuente)) {
            return $destino;
        }

        try {
            if (! is_dir(dirname($destino))) {
                mkdir(dirname($destino), 0755, true);
            }

            $this->convertir($fuente, $destino, $ancho);
        } catch (Throwable $e) {
            Log::warning('No se pudo generar la variante de imagen.', ['ruta' => $original, 'ancho' => $ancho, 'error' => $e->getMessage()]);

            return null;
        }

        return is_file($destino) ? $destino : null;
    }

    /** Achica (sin agrandar nunca) y guarda en WebP. Imagick si está; si no, GD. */
    private function convertir(string $fuente, string $destino, int $ancho): void
    {
        $temporal = $destino.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (extension_loaded('imagick')) {
            $img = new \Imagick($fuente);
            $img->autoOrient();
            if ($img->getImageWidth() > $ancho) {
                $img->thumbnailImage($ancho, 0);
            }
            $img->stripImage();
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality(self::CALIDAD);
            $img->writeImage('webp:'.$temporal);
            $img->clear();
        } else {
            $datos = file_get_contents($fuente);
            $img = imagecreatefromstring($datos);
            if ($img === false) {
                throw new \RuntimeException('Formato no soportado');
            }
            if (imagesx($img) > $ancho) {
                $img = imagescale($img, $ancho);
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            imagewebp($img, $temporal, self::CALIDAD);
            imagedestroy($img);
        }

        // Atómico: dos pedidos a la vez no dejan un archivo a medio escribir.
        rename($temporal, $destino);
    }
}
