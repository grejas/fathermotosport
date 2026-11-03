<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\PrecioFinal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'category_id',
        'sku',
        'barcode',
        'name',
        'color',
        'slug',
        'short_description',
        'description',
        'price',
        'sale_price',
        'cost',
        'weight',
        'minimum_stock',
        'is_active',
        'is_featured',
        'is_new',
        'is_popular',
        'specs',
        'certification',
        'spin_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'minimum_stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_popular' => 'boolean',
        'specs' => 'array',
    ];

    protected $appends = [
        'discount_percent',
        'has_3d_model',
    ];

    protected static function booted(): void
    {
        // La base borra la galería en cascada, sin pasar por el modelo: se borra antes
        // elemento por elemento para que se vayan también sus archivos del disco. Con la
        // papelera (soft delete) no: el producto se puede restaurar con sus fotos.
        // Lo mismo con las reseñas, que se llevan las fotos de sus clientes.
        static::forceDeleting(function (Product $product) {
            $product->images()->get()->each->delete();
            $product->reviews()->get()->each->delete();
        });
    }

    // Relaciones
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function model3d(): HasOne
    {
        return $this->hasOne(Product3dModel::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function visorColors(): BelongsToMany
    {
        return $this->belongsToMany(VisorColor::class, 'product_visor_colors');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('variants', fn (Builder $q) => $q->where('stock', '>', 0));
    }

    /**
     * Filtro dinámico para listados de catálogo.
     * Acepta: category, brand, min_price, max_price, search, is_new, is_popular, is_featured.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['brand'] ?? null, fn (Builder $q, $v) => $q->where('brand_id', $v))
            ->when($filters['min_price'] ?? null, fn (Builder $q, $v) => $q->where('price', '>=', $v))
            ->when($filters['max_price'] ?? null, fn (Builder $q, $v) => $q->where('price', '<=', $v))
            ->when($filters['is_new'] ?? null, fn (Builder $q) => $q->where('is_new', true))
            ->when($filters['is_popular'] ?? null, fn (Builder $q) => $q->where('is_popular', true))
            ->when($filters['is_featured'] ?? null, fn (Builder $q) => $q->where('is_featured', true))
            ->when($filters['search'] ?? null, function (Builder $q, $v) {
                $q->where(function (Builder $sub) use ($v) {
                    $sub->where('name', 'like', "%{$v}%")
                        ->orWhere('sku', 'like', "%{$v}%")
                        ->orWhere('short_description', 'like', "%{$v}%");
                });
            });
    }

    // Accessors
    /**
     * El "-X %" del precio final: el del precio propio (entero) o el de la categoría
     * (puede tener decimales, ej. 7,5). Ver PrecioFinal.
     */
    public function getDiscountPercentAttribute(): int|float
    {
        return $this->precioFinal()['discount_percent'];
    }

    /** Lo que paga el cliente, ya con el mejor descuento (propio o de categoría). */
    public function getFinalPriceAttribute(): string
    {
        return $this->precioFinal()['final_price'];
    }

    /** "product", "category" o null: de dónde sale el descuento del precio final. */
    public function getDiscountSourceAttribute(): ?string
    {
        return $this->precioFinal()['discount_source'];
    }

    /** @return array{final_price: string, discount_percent: int|float, discount_source: ?string} */
    public function precioFinal(): array
    {
        return app(PrecioFinal::class)->de($this);
    }

    /**
     * La imagen del producto en tarjetas, carrito, pedidos, upsell y correos: nunca un
     * video. En orden: la imagen marcada "Principal"; si no hay, la primera imagen de la
     * galería; si solo hay GIF, el primer fotograma del primero (o el GIF, si no se pudo
     * sacar); si solo hay videos, el primer poster. Si no hay nada de eso, null.
     */
    public function getPrimaryImageAttribute(): ?string
    {
        $galeria = $this->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        $imagenes = $galeria->filter(fn (ProductImage $m) => $m->esImagen());

        $ruta = ($imagenes->firstWhere('is_primary', true) ?? $imagenes->first())?->url;

        if (! $ruta && $gif = $galeria->first(fn (ProductImage $m) => $m->esGif())) {
            $ruta = $gif->thumbnail_url ?: $gif->url;
        }

        $ruta ??= $galeria->first(fn (ProductImage $m) => $m->esVideo() && filled($m->poster_url))?->poster_url;

        // La columna guarda ruta relativa; se devuelve URL pública absoluta.
        return ProductImage::publicUrl($ruta);
    }

    public function getHas3dModelAttribute(): bool
    {
        return $this->relationLoaded('model3d')
            ? ! is_null($this->model3d)
            : $this->model3d()->exists();
    }

    public function getCurrentPriceAttribute(): string
    {
        return $this->final_price;
    }

    /**
     * Stock total sumando las variantes activas.
     * El stock vive en product_variants, no en products.
     */
    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->where('is_active', true)->sum('stock');
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->total_stock > 0;
    }
}
