<?php

namespace App\Models;

use App\Services\FrontendRevalidator;
use App\Support\VideoInicioRapido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Banner del sitio: una imagen o un video subido desde el panel, con título, texto y
 * botón opcionales. El contenido se carga en español y la web lo traduce al vuelo.
 * En el hero solo se usan el medio y el botón: el hero conserva sus textos fijos.
 */
class Banner extends Model
{
    /** Tag de caché compartido con la web (lib/api/banners.ts, BANNERS_TAG). */
    public const REVALIDATE_TAG = 'banners';

    /**
     * Posiciones que ofrece el panel. 'sidebar' sigue siendo válida en la base (hay
     * filas viejas) pero no se dibuja en ninguna página, así que no se ofrece.
     */
    public const POSICIONES = [
        'hero' => 'Hero (inicio de la portada)',
        'home_after_categories' => 'Debajo de categorías',
        'footer' => 'Footer',
    ];

    /** Columnas que guardan un archivo del disco public. */
    public const ARCHIVOS = ['image_url', 'video_path', 'video_mobile_path', 'poster_path'];

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'media_type',
        'video_path',
        'video_mobile_path',
        'poster_path',
        'video_with_sound',
        'link_url',
        'button_text',
        'position',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'video_with_sound' => 'boolean',
    ];

    protected $attributes = [
        'media_type' => 'image',
        'video_with_sound' => false,
    ];

    protected static function booted(): void
    {
        // Reemplazar o vaciar un archivo en el panel no lo borra del disco (Filament no
        // lo hace): se borra acá el anterior. Y al borrar el banner, todos los suyos.
        static::updated(function (Banner $banner) {
            foreach (self::ARCHIVOS as $columna) {
                if ($banner->wasChanged($columna)) {
                    self::borrarArchivo($banner->getOriginal($columna), $banner->getKey());
                }
            }
        });

        static::deleted(function (Banner $banner) {
            foreach (self::ARCHIVOS as $columna) {
                self::borrarArchivo($banner->getAttribute($columna), $banner->getKey());
            }
        });

        // Un MP4 con el índice (moov) al final obliga al navegador a bajarlo entero, o a
        // pedir el final aparte, antes de mostrar un fotograma: se reordena al guardarlo.
        static::saved(function (Banner $banner) {
            foreach (['video_path', 'video_mobile_path'] as $columna) {
                if ($banner->wasRecentlyCreated || $banner->wasChanged($columna)) {
                    self::prepararVideo($banner->getAttribute($columna));
                }
            }
        });

        // La web cachea los banners 10 minutos: se le avisa para que el cambio se vea ya.
        static::saved(fn () => app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG));
        static::deleted(fn () => app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    public function esVideo(): bool
    {
        return $this->media_type === 'video';
    }

    /**
     * El título es opcional (solo lo usa "Debajo de categorías"). La columna sigue siendo
     * NOT NULL: vacío se guarda como cadena vacía y la API lo devuelve como null.
     */
    public function setTitleAttribute(?string $valor): void
    {
        $this->attributes['title'] = trim((string) $valor);
    }

    /**
     * Pone el índice del video al principio (ver VideoInicioRapido). Si no es un MP4/MOV,
     * ya estaba bien o algo falla, el archivo queda como estaba: nunca impide guardar.
     */
    public static function prepararVideo(?string $ruta): bool
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $ruta)) {
            return false;
        }

        try {
            $disco = Storage::disk('public');
            if (! $disco->exists($ruta)) {
                return false;
            }

            $cambiado = VideoInicioRapido::aplicar($disco->path($ruta));
            if ($cambiado) {
                Log::info('Video de banner reordenado para inicio rápido (moov al principio).', ['ruta' => $ruta]);
            }

            return $cambiado;
        } catch (\Throwable $e) {
            Log::warning('No se pudo preparar el video del banner para inicio rápido.', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Borra un archivo del disco public. No toca URLs externas (http…) ni un archivo
     * que otro banner siga usando (por ejemplo, uno duplicado a mano en la base).
     */
    private static function borrarArchivo(?string $ruta, mixed $exceptoId): void
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $ruta)) {
            return;
        }

        $enUso = self::query()
            ->whereKeyNot($exceptoId)
            ->where(function (Builder $q) use ($ruta) {
                foreach (self::ARCHIVOS as $columna) {
                    $q->orWhere($columna, $ruta);
                }
            })
            ->exists();

        if (! $enUso) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
