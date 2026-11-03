<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imagen de una campaña de correo. Como las fotos de reseñas (FotoDeResena), nunca se
 * guarda el archivo tal como llega: se valida el tipo real (getimagesize, no la
 * extensión), se decodifica con GD y se vuelve a dibujar desde cero, así no sobrevive el
 * EXIF ni nada escondido en el archivo. El nombre lo genera el servidor.
 *
 * Sale en PNG si tiene transparencia y en JPEG si no (los formatos que muestran Gmail,
 * Outlook y Apple Mail; WebP no), con el lado mayor en 1200 px (2x del ancho del correo).
 * Se guarda en el disco public y en el correo va con URL https absoluta y codificada
 * (ImagenDeCorreo).
 */
class ImagenDeCampana
{
    /** Tamaño máximo del archivo subido, en KB (5 MB). */
    public const MAX_KB = 5120;

    public const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    public const CARPETA = 'campanas';

    public const LADO_MAXIMO = 1200;

    public const CALIDAD = 85;

    /** Reglas de validación del archivo (tipo real, tamaño y píxeles). */
    public static function reglas(): array
    {
        return ['file', 'mimetypes:'.implode(',', self::TIPOS), 'max:'.self::MAX_KB, FotoDeResena::reglaDeDimensiones()];
    }

    /** Re-codifica y guarda. Devuelve la ruta en el disco public (campanas/<uuid>.jpg|png). */
    public static function guardar(UploadedFile $archivo): string
    {
        $info = @getimagesize($archivo->getRealPath());
        if (! $info || ! in_array($info['mime'] ?? '', self::TIPOS, true) || $info[0] * $info[1] > FotoDeResena::MAX_PIXELES) {
            throw new RuntimeException('La imagen no es un JPG, PNG o WebP válido.');
        }

        $original = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if (! $original) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $imagen = self::reducir($original);
        $png = self::tieneTransparencia($imagen);
        $ruta = self::CARPETA.'/'.Str::uuid().($png ? '.png' : '.jpg');

        ob_start();
        if ($png) {
            imagepng($imagen, null, 6);
        } else {
            imagejpeg($imagen, null, self::CALIDAD);
        }
        $bytes = (string) ob_get_clean();

        if ($bytes === '' || ! Storage::disk('public')->put($ruta, $bytes)) {
            throw new RuntimeException('No se pudo guardar la imagen.');
        }

        return $ruta;
    }

    /** URL para el correo (https, absoluta y codificada), o null si no hay o no existe. */
    public static function url(?string $ruta): ?string
    {
        return filled($ruta) ? ImagenDeCorreo::url(Storage::disk('public')->url($ruta), comprobarArchivo: true) : null;
    }

    private static function reducir(\GdImage $imagen): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $copia = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($copia, false);
        imagesavealpha($copia, true);
        imagefill($copia, 0, 0, imagecolorallocatealpha($copia, 0, 0, 0, 127));
        imagecopyresampled($copia, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $copia;
    }

    /** Si algún píxel (muestreado) no es opaco. */
    private static function tieneTransparencia(\GdImage $imagen): bool
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $paso = max(1, (int) floor(min($ancho, $alto) / 40));

        for ($y = 0; $y < $alto; $y += $paso) {
            for ($x = 0; $x < $ancho; $x += $paso) {
                if (((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
