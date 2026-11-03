<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fotos que suben los clientes con su reseña. Nunca se guarda el archivo tal como
 * llega: se decodifica con GD y se vuelve a dibujar desde cero (WebP, o JPEG si el
 * servidor no tiene WebP), así no sobrevive el EXIF (GPS, cámara) ni nada escondido
 * dentro o pegado al final del archivo. El nombre lo genera el servidor.
 */
class FotoDeResena
{
    public const MAX_FOTOS = 3;

    /** Tamaño máximo de cada archivo subido, en KB (5 MB). */
    public const MAX_KB = 5120;

    public const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Más que esto no se decodifica: una imagen chica en bytes puede pedir cientos de MB. */
    public const MAX_PIXELES = 25_000_000;

    public const LADO_MAXIMO = 1600;

    public const LADO_MINIATURA = 400;

    public const CALIDAD = 82;

    /** Pendientes, en el disco privado (local). */
    public const CARPETA_PENDIENTE = 'resenas/pendientes';

    /** Aprobadas, en el disco public. */
    public const CARPETA_PUBLICA = 'resenas';

    /** Reglas de validación de `photos` y `photos.*`. */
    public static function reglas(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.self::MAX_FOTOS],
            'photos.*' => [
                'file',
                'mimetypes:'.implode(',', self::TIPOS),
                'max:'.self::MAX_KB,
                self::reglaDeDimensiones(),
            ],
        ];
    }

    /** Que la imagen se pueda leer y no sea desproporcionadamente grande al decodificarla. */
    public static function reglaDeDimensiones(): Closure
    {
        return function (string $atributo, mixed $archivo, Closure $fallar) {
            if (! $archivo instanceof UploadedFile) {
                return;
            }

            $info = @getimagesize($archivo->getRealPath());
            if (! $info || ! in_array($info['mime'] ?? '', self::TIPOS, true)) {
                $fallar('La foto no es una imagen JPG, PNG o WebP válida.');

                return;
            }

            if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELES) {
                $fallar('La foto es demasiado grande en píxeles.');
            }
        };
    }

    /** Si este servidor puede escribir WebP; si no, las fotos se guardan como JPEG. */
    public static function soportaWebp(): bool
    {
        return function_exists('imagewebp') && (imagetypes() & IMG_WEBP);
    }

    /**
     * Re-codifica la foto y la guarda pendiente (disco privado), con su miniatura.
     *
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public static function guardarPendiente(UploadedFile $archivo, string $atributo = 'photos'): array
    {
        $original = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if (! $original) {
            throw ValidationException::withMessages([$atributo => 'No se pudo leer la foto.']);
        }

        $imagen = self::enderezar($original, $archivo);
        $grande = self::reducir($imagen, self::LADO_MAXIMO);
        $miniatura = self::reducir($grande, self::LADO_MINIATURA);

        $webp = self::soportaWebp();
        $extension = $webp ? 'webp' : 'jpg';
        $nombre = (string) Str::uuid();
        $rutas = [
            'path' => self::CARPETA_PENDIENTE."/{$nombre}.{$extension}",
            'thumb_path' => self::CARPETA_PENDIENTE."/{$nombre}-min.{$extension}",
        ];

        $disco = Storage::disk('local');
        try {
            foreach (['path' => $grande, 'thumb_path' => $miniatura] as $columna => $gd) {
                if (! $disco->put($rutas[$columna], self::codificar($gd, $webp))) {
                    throw new \RuntimeException('No se pudo guardar la foto de la reseña.');
                }
            }
        } catch (\Throwable $e) {
            $disco->delete(array_values($rutas));

            throw $e;
        }

        return [...$rutas, 'width' => imagesx($grande), 'height' => imagesy($grande)];
    }

    /** Borra los archivos de fotos que se llegaron a guardar (si falla el alta de la reseña). */
    public static function borrarPendientes(array $guardadas): void
    {
        $rutas = [];
        foreach ($guardadas as $foto) {
            $rutas[] = $foto['path'];
            $rutas[] = $foto['thumb_path'];
        }

        if ($rutas) {
            Storage::disk('local')->delete($rutas);
        }
    }

    /**
     * Aplica la orientación EXIF de las fotos de celular (el EXIF se descarta al
     * re-codificar, así que sin esto quedarían giradas). Sin la extensión exif, tal cual.
     */
    private static function enderezar(\GdImage $imagen, UploadedFile $archivo): \GdImage
    {
        $info = @getimagesize($archivo->getRealPath());
        if (! function_exists('exif_read_data') || ! $info || $info['mime'] !== 'image/jpeg') {
            return $imagen;
        }

        $exif = @exif_read_data($archivo->getRealPath());
        $orientacion = (int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1);

        $girar = fn (\GdImage $img, int $grados): \GdImage => imagerotate($img, $grados, 0) ?: $img;

        switch ($orientacion) {
            case 2:
                imageflip($imagen, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $imagen = $girar($imagen, 180);
                break;
            case 4:
                imageflip($imagen, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $imagen = $girar($imagen, -90);
                imageflip($imagen, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $imagen = $girar($imagen, -90);
                break;
            case 7:
                $imagen = $girar($imagen, -90);
                imageflip($imagen, IMG_FLIP_VERTICAL);
                break;
            case 8:
                $imagen = $girar($imagen, 90);
                break;
        }

        return $imagen;
    }

    /** Copia nueva con el lado mayor en `$lado` como máximo (nunca agranda). Conserva la transparencia. */
    private static function reducir(\GdImage $imagen, int $lado): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, $lado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $copia = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($copia, false);
        imagesavealpha($copia, true);
        imagefill($copia, 0, 0, imagecolorallocatealpha($copia, 0, 0, 0, 127));
        imagecopyresampled($copia, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $copia;
    }

    private static function codificar(\GdImage $imagen, bool $webp): string
    {
        ob_start();
        if ($webp) {
            imagewebp($imagen, null, self::CALIDAD);
        } else {
            // JPEG no tiene transparencia: se apoya sobre blanco.
            $fondo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
            imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
            imagecopy($fondo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
            imagejpeg($fondo, null, self::CALIDAD);
        }

        return (string) ob_get_clean();
    }
}
