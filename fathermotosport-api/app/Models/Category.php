<?php

namespace App\Models;

use App\Services\FrontendRevalidator;
use App\Services\PrecioFinal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'discount_percent',
        'discount_active',
        'discount_starts_at',
        'discount_ends_at',
        'notify_customers',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'size_options' => 'array',
        'discount_percent' => 'decimal:2',
        'discount_active' => 'boolean',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
        'notify_customers' => 'boolean',
    ];

    /** Rango del porcentaje de descuento de categoría (con decimales, ej. 7,5). */
    public const DESCUENTO_MIN = 0.5;

    public const DESCUENTO_MAX = 90;

    /** Estados del descuento, para el panel. */
    public const DESCUENTO_APAGADO = 'apagado';

    public const DESCUENTO_PROGRAMADO = 'programado';

    public const DESCUENTO_VIGENTE = 'vigente';

    public const DESCUENTO_VENCIDO = 'vencido';

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

        // Los precios se calculan con las categorías leídas una vez por petición: si se
        // guarda una categoría, lo que se calcule después en la misma petición la relee.
        static::saved(fn () => PrecioFinal::olvidar());
        static::deleted(fn () => PrecioFinal::olvidar());
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

    /** Productos que no reciben el descuento de esta categoría (conservan solo el propio). */
    public function productosExcluidos(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_discount_exclusions');
    }

    /**
     * Si el descuento de la categoría aplica en este momento: interruptor encendido,
     * porcentaje cargado y $ahora dentro de [inicio, fin] (cada fecha es opcional).
     * Se evalúa en cada consulta: al pasar el fin deja de aplicar aunque la tarea
     * programada todavía no haya apagado el interruptor.
     */
    public function descuentoVigente(?CarbonInterface $ahora = null): bool
    {
        return $this->estadoDelDescuento($ahora) === self::DESCUENTO_VIGENTE;
    }

    public function estadoDelDescuento(?CarbonInterface $ahora = null): string
    {
        $ahora ??= now();

        if (! $this->discount_active || (float) $this->discount_percent <= 0) {
            return self::DESCUENTO_APAGADO;
        }

        if ($this->discount_ends_at && $ahora->greaterThan($this->discount_ends_at)) {
            return self::DESCUENTO_VENCIDO;
        }

        if ($this->discount_starts_at && $ahora->lessThan($this->discount_starts_at)) {
            return self::DESCUENTO_PROGRAMADO;
        }

        return self::DESCUENTO_VIGENTE;
    }

    /**
     * Ids de la categoría y de todas sus subcategorías. Pocos niveles en la práctica; el
     * límite evita un bucle infinito si alguien armara un ciclo de padres en el panel.
     *
     * @return array<int, int>
     */
    public static function idsConDescendientes(int $categoryId): array
    {
        $ids = [$categoryId];
        $nivel = [$categoryId];

        for ($i = 0; $i < 10 && $nivel; $i++) {
            $nivel = self::whereIn('parent_id', $nivel)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $nivel);
        }

        return $ids;
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
