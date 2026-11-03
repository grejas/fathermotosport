<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class Review extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'title',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    protected static function booted(): void
    {
        // La base borra las fotos en cascada, sin pasar por el modelo: se borran antes
        // una por una para que se vayan también sus archivos del disco.
        static::deleting(function (Review $review) {
            $review->images()->get()->each->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Todas sus fotos (pendientes y aprobadas), en orden. */
    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order');
    }

    /** Las fotos que se publican (si la reseña también está aprobada). */
    public function approvedImages(): HasMany
    {
        return $this->images()->where('status', ReviewImage::APROBADA);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /** Sin aprobar o con alguna foto pendiente. */
    public function tienePendientes(): bool
    {
        return ! $this->is_approved
            || $this->images()->where('status', ReviewImage::PENDIENTE)->exists();
    }

    /**
     * "Aprobar todo": la reseña y sus fotos pendientes, en una sola transacción.
     *
     * @return array{resena: bool, fotos: int, fallidas: list<string>}
     */
    public function aprobarTodo(): array
    {
        return DB::transaction(function () {
            $resena = ! $this->is_approved;
            if ($resena) {
                $this->update(['is_approved' => true]);
            }

            return ['resena' => $resena, ...$this->moverFotosPendientes()];
        });
    }

    /**
     * Solo las fotos pendientes, en una transacción.
     *
     * @return array{fotos: int, fallidas: list<string>}
     */
    public function aprobarFotosPendientes(): array
    {
        return DB::transaction(fn () => $this->moverFotosPendientes());
    }

    /**
     * Aprueba sus fotos pendientes con ReviewImage::aprobar(), dentro de la transacción
     * que la llama, sin anidar otra: si esa se deshace, también se quitan las copias públicas.
     * Una foto que no se puede mover (archivo faltante, disco lleno) se queda pendiente y
     * entera, y se informa como "Foto N" (su posición en la ventana "Ver"); las demás se
     * aprueban igual. Un error de la base sí deshace todo.
     *
     * @return array{fotos: int, fallidas: list<string>}
     */
    private function moverFotosPendientes(): array
    {
        $fotos = 0;
        $fallidas = [];

        // Bloqueadas: dos admins a la vez no intentan mover los mismos archivos.
        foreach ($this->images()->lockForUpdate()->get()->values() as $i => $foto) {
            if ($foto->estaAprobada()) {
                continue;
            }

            try {
                $foto->aprobar();
                $fotos++;
            } catch (QueryException $e) {
                throw $e;
            } catch (\Throwable $e) {
                report($e);
                $fallidas[] = 'Foto '.($i + 1);
            }
        }

        return ['fotos' => $fotos, 'fallidas' => $fallidas];
    }
}
