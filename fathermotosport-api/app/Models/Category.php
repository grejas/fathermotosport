<?php

namespace App\Models;

use App\Services\FrontendRevalidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Tipo de stock (size_type). Las tallas de cada tipo las resuelve SizeCatalog.
     *  - inherit: hereda de la categoría padre; en una raíz, sin tallas.
     *  - none:    solo stock, sin tallas (corta la herencia).
     *  - letters: size_options = {"sizes": [...]}, de SizeCatalog::LETTER_ORDER.
     *  - numeric: size_options = {"from": 36, "to": 47, "step": 1 | 0.5}.
     *  - custom:  size_options = {"sizes": [...]}, en el orden en que se escribieron.
     */
    public const SIZE_INHERIT = 'inherit';

    public const SIZE_NONE = 'none';

    public const SIZE_LETTERS = 'letters';

    public const SIZE_NUMERIC = 'numeric';

    public const SIZE_CUSTOM = 'custom';

    public const SIZE_TYPES = [
        self::SIZE_INHERIT => 'Hereda de la categoría padre',
        self::SIZE_NONE => 'Solo stock (sin tallas)',
        self::SIZE_LETTERS => 'Letras',
        self::SIZE_NUMERIC => 'Números',
        self::SIZE_CUSTOM => 'Lista propia',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'image_url',
        'description',
        'is_active',
        'sort_order',
        'size_type',
        'size_options',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'size_options' => 'array',
    ];

    protected $attributes = [
        'size_type' => self::SIZE_INHERIT,
    ];

    /** Tag de caché de la web (CATEGORIES_TAG en lib/api/categories.ts). */
    public const REVALIDATE_TAG = 'categories';

    protected static function booted(): void
    {
        // La web cachea los menús de categorías 10 minutos: se le avisa para que crear,
        // editar, activar/desactivar o borrar una categoría se vea ya.
        // created/updated y no saved: el panel guarda dos veces al crear y un guardado sin
        // cambios no debe avisar.
        static::created(fn () => app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG));
        static::updated(fn () => app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG));
        static::deleted(fn () => app(FrontendRevalidator::class)->revalidate(self::REVALIDATE_TAG));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
