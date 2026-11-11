<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\ArchivoPublico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Un elemento de la galería de un producto: imagen, GIF o video corto, en el orden que
 * se arrastra en el panel (sort_order).
 *
 * El tipo no se elige a mano: sale del archivo al guardarlo. Un video puede tener poster
 * y sonido opcional; un GIF guarda en thumbnail_url su primer fotograma estático (para
 * las miniaturas, que así no bajan el GIF entero). Solo una imagen puede ser principal.
 */
class ProductImage extends Model
{
    use HasFactory, HasUuid;

    public const IMAGEN = 'image';

    public const GIF = 'gif';

    public const VIDEO = 'video';

    /** Tope de un video o GIF de la galería, en KB (50 MB). Las imágenes no tienen uno propio. */
    public const MAX_VIDEO_GIF_KB = 51200;

    /** Columnas que guardan un archivo del disco public. */
    public const ARCHIVOS = ['url', 'thumbnail_url', 'poster_url'];

    /** Para reconocer un video por su nombre cuando el contenido no alcanza. */
    private const EXTENSIONES_DE_VIDEO = ['mp4', 'm4v', 'mov', 'qt', 'webm', 'ogv', 'avi', 'mkv', 'wmv', 'mpg', 'mpeg', '3gp', 'flv'];

    protected $fillable = [
        'product_id',
        'color',
        'url',
        'media_type',
        'thumbnail_url',
        'poster_url',
        'sort_order',
        'is_primary',
        'with_sound',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
        'with_sound' => 'boolean',
    ];

    protected $attributes = [
        'media_type' => self::IMAGEN,
        'with_sound' => false,
    ];

    protected static function booted(): void
    {
        // El tipo y lo que depende de él se deciden acá, con el archivo ya guardado: así
        // no queda un "video" con un PNG, ni un poster o un "Principal" donde no van.
        static::saving(function (ProductImage $medio) {
            $archivoNuevo = ! $medio->exists || $medio->isDirty('url');

            if ($archivoNuevo) {
                $medio->media_type = self::tipoDeArchivo($medio->url);
            }
            // Un toggle que el panel no llegó a tocar llega como null.
            $medio->with_sound = (bool) $medio->with_sound;
            $medio->is_primary = (bool) $medio->is_primary;

            if ($medio->media_type !== self::VIDEO) {
                $medio->poster_url = null;
                $medio->with_sound = false;
            }
            if ($medio->media_type !== self::IMAGEN) {
                $medio->is_primary = false;
            }
            if ($archivoNuevo) {
                $medio->thumbnail_url = $medio->media_type === self::GIF ? self::fotogramaDeGif($medio->url) : null;
            }
        });

        // Reemplazar o quitar un archivo en el panel no lo borra del disco (Filament no
        // lo hace): se borra acá el anterior. Y al quitar el elemento, todos los suyos.
        static::updated(function (ProductImage $medio) {
            foreach (self::ARCHIVOS as $columna) {
                if ($medio->wasChanged($columna)) {
                    self::borrarArchivo($medio->getOriginal($columna), $medio->getKey());
                }
            }
        });

        static::deleted(function (ProductImage $medio) {
            foreach (self::ARCHIVOS as $columna) {
                self::borrarArchivo($medio->getAttribute($columna), $medio->getKey());
            }
        });

        // Un MP4 con el índice (moov) al final no arranca hasta bajarse entero: se reordena.
        static::saved(function (ProductImage $medio) {
            if ($medio->esVideo() && ($medio->wasRecentlyCreated || $medio->wasChanged('url'))) {
                ArchivoPublico::prepararVideo($medio->url, 'producto');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function esImagen(): bool
    {
        return ($this->media_type ?? self::IMAGEN) === self::IMAGEN;
    }

    public function esGif(): bool
    {
        return $this->media_type === self::GIF;
    }

    public function esVideo(): bool
    {
        return $this->media_type === self::VIDEO;
    }

    /** image, gif o video según un tipo MIME; null si no es ninguno de los tres. */
    public static function tipoPorMime(?string $mime): ?string
    {
        $mime = strtolower(trim((string) $mime));

        return match (true) {
            str_starts_with($mime, 'video/'), $mime === 'application/mp4' => self::VIDEO,
            $mime === 'image/gif' => self::GIF,
            str_starts_with($mime, 'image/') => self::IMAGEN,
            default => null,
        };
    }

    /** image, gif o video según la extensión del nombre (lo que no es video ni GIF es imagen). */
    public static function tipoPorExtension(?string $nombre): string
    {
        $extension = strtolower(pathinfo(parse_url((string) $nombre, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::EXTENSIONES_DE_VIDEO, true) => self::VIDEO,
            $extension === 'gif' => self::GIF,
            default => self::IMAGEN,
        };
    }

    /**
     * El tipo de un archivo guardado: por su contenido y, si el contenido no lo dice (un
     * formato que el servidor no reconoce, una URL externa), por la extensión.
     */
    public static function tipoDeArchivo(?string $ruta): string
    {
        if (ArchivoPublico::esLocal($ruta)) {
            $mime = rescue(fn () => Storage::disk('public')->mimeType(trim((string) $ruta)), null, false);
            if ($tipo = self::tipoPorMime($mime ?: null)) {
                return $tipo;
            }
        }

        return self::tipoPorExtension($ruta);
    }

    /**
     * Dónde va el fotograma de un GIF: su nombre más un hash de la ruta, para que dos GIF
     * con el mismo nombre en carpetas distintas no compartan fotograma.
     */
    public static function rutaDeFotograma(string $rutaDelGif): string
    {
        $rutaDelGif = trim($rutaDelGif);

        return 'products/fotogramas/'.pathinfo($rutaDelGif, PATHINFO_FILENAME).'-'.substr(md5($rutaDelGif), 0, 8).'.png';
    }

    /**
     * Guarda el primer fotograma de un GIF como PNG y devuelve su ruta (o null si no hay
     * GD o el GIF no se puede leer: la miniatura muestra entonces una marca "GIF").
     */
    public static function fotogramaDeGif(?string $ruta): ?string
    {
        if (! ArchivoPublico::esLocal($ruta) || ! function_exists('imagecreatefromgif')) {
            return null;
        }

        $ruta = trim((string) $ruta);

        try {
            $disco = Storage::disk('public');
            if (! $disco->exists($ruta)) {
                return null;
            }

            $gif = @imagecreatefromgif($disco->path($ruta));
            if (! $gif) {
                return null;
            }

            ob_start();
            imagepng($gif);
            $png = ob_get_clean();
            imagedestroy($gif);

            $destino = self::rutaDeFotograma($ruta);
            $disco->put($destino, $png, 'public');

            return $destino;
        } catch (\Throwable $e) {
            Log::warning('No se pudo sacar el fotograma del GIF.', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return null;
        }
    }

    // NOTA: no se define un accessor sobre `url` para no sombrear la columna cruda
    // que Filament (FileUpload) necesita en formato relativo. La conversión a URL
    // pública absoluta se hace en la capa de presentación (API Resources) con el
    // helper estático publicUrl().

    /**
     * Convierte una ruta relativa del disco público en URL absoluta.
     * Idempotente: si ya es una URL (http) la devuelve sin cambios.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    /** Borra un archivo del disco public, salvo que otro elemento de galería lo siga usando. */
    private static function borrarArchivo(?string $ruta, mixed $exceptoId): void
    {
        ArchivoPublico::borrarSiNoSeUsa($ruta, fn (string $ruta): bool => self::query()
            ->whereKeyNot($exceptoId)
            ->where(function (Builder $q) use ($ruta) {
                foreach (self::ARCHIVOS as $columna) {
                    $q->orWhere($columna, $ruta);
                }
            })
            ->exists());
    }
}
