<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FlashPromo extends Model
{
    protected $fillable = [
        'starts_at',
        'ends_at',
        'promo_text',
        'is_active',
        'is_recurring',
        'rest_minutes',
        'recurring_group_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'rest_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Una promo recurrente sin cadena asignada estrena un recurring_group_id.
        // Las ocurrencias que genera el scheduler ya vienen con su group_id, así que
        // este hook no las sobreescribe.
        static::saving(function (self $promo): void {
            if ($promo->is_recurring && empty($promo->recurring_group_id)) {
                $promo->recurring_group_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Categorías a las que aplica la promo. Si no tiene ninguna, aplica a TODA la tienda.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_flash_promo');
    }

    /**
     * Promociones vigentes AHORA: activas y con now() dentro de [starts_at, ends_at].
     */
    public function scopeActiveNow(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? Carbon::now();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    /**
     * Todas las promociones vigentes en este momento, más reciente (starts_at) primero.
     *
     * @return Collection<int, static>
     */
    public static function activeNowList(?Carbon $now = null): Collection
    {
        return static::query()
            ->with('categories:id,slug')
            ->activeNow($now)
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * Forma pública de una promo para el endpoint /flash-promo.
     *
     * @return array{id: int, promo_text: ?string, ends_at: ?string, category_ids: array<int, int>, category_slugs: array<int, string>, applies_to_all: bool}
     */
    public function toPublicArray(): array
    {
        $categories = $this->categories;

        return [
            'id' => $this->id,
            'promo_text' => $this->promo_text,
            'ends_at' => $this->ends_at->toIso8601String(),
            'category_ids' => $categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'category_slugs' => $categories->pluck('slug')->all(),
            // Sin categorías => aplica a toda la tienda.
            'applies_to_all' => $categories->isEmpty(),
        ];
    }
}
