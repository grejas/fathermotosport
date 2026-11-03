<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\FotoDeResena;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Foto de una reseña. Pendiente: vive en el disco privado (local), sin URL pública.
 * Aprobada: se movió al disco public. Se publica solo si además la reseña está aprobada.
 */
class ReviewImage extends Model
{
    use HasUuid;

    public const PENDIENTE = 'pending';

    public const APROBADA = 'approved';

    protected $fillable = [
        'review_id',
        'path',
        'thumb_path',
        'width',
        'height',
        'sort_order',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Los nombres los genera el servidor (uuid) y no se comparten entre filas:
        // al borrar la foto se borran sus dos archivos, estén donde estén.
        static::deleted(function (ReviewImage $foto) {
            $foto->disco()->delete(array_filter([$foto->path, $foto->thumb_path]));
        });
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APROBADA);
    }

    public function estaAprobada(): bool
    {
        return $this->status === self::APROBADA;
    }

    /** Donde están sus archivos: el público si está aprobada, el privado si no. */
    public function disco(): Filesystem
    {
        return Storage::disk($this->estaAprobada() ? 'public' : 'local');
    }

    /**
     * Mueve los archivos al disco público y la marca aprobada. Copia primero y borra
     * los privados al final: si algo falla a mitad, la foto sigue pendiente y entera.
     * Dentro de una transacción, los privados se borran cuando se confirma y las copias
     * públicas se quitan si se deshace: la fila nunca apunta a un archivo que no está.
     */
    public function aprobar(): void
    {
        if ($this->estaAprobada()) {
            return;
        }

        $privado = Storage::disk('local');
        $publico = Storage::disk('public');
        $nuevas = [];

        try {
            foreach (['path', 'thumb_path'] as $columna) {
                $destino = FotoDeResena::CARPETA_PUBLICA.'/'.basename($this->{$columna});
                $contenido = $privado->get($this->{$columna});
                if ($contenido === null || ! $publico->put($destino, $contenido, 'public')) {
                    throw new \RuntimeException("No se pudo copiar {$this->{$columna}} al disco public.");
                }
                $nuevas[$columna] = $destino;
            }

            $anteriores = [$this->path, $this->thumb_path];
            $this->update([...$nuevas, 'status' => self::APROBADA, 'approved_at' => now()]);
        } catch (\Throwable $e) {
            $publico->delete(array_values($nuevas));

            throw $e;
        }

        DB::afterRollBack(fn () => $publico->delete(array_values($nuevas)));
        DB::afterCommit(fn () => $privado->delete($anteriores));
    }
}
