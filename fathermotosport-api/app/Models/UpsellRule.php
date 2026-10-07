<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Quien compró X, se le ofrece Y con descuento."
 *
 * X (disparador) es un producto o una categoría; Y (oferta) también. Una categoría
 * incluye sus subcategorías y se resuelve al momento de mostrar las ofertas (ver
 * UpsellService::ofertasPara): así un producto agregado después a la categoría entra
 * solo, sin editar la regla.
 */
class UpsellRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'trigger_product_id',
        'trigger_category_id',
        'offer_product_id',
        'offer_category_id',
        'discount_percent',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function triggerProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'trigger_product_id');
    }

    public function triggerCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'trigger_category_id');
    }

    public function offerProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'offer_product_id');
    }

    public function offerCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'offer_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** ¿Interviene una categoría, de cualquiera de los dos lados? */
    public function esPorCategoria(): bool
    {
        return $this->trigger_category_id !== null || $this->offer_category_id !== null;
    }

    /** ¿Lo que se ofrece es una categoría (y no un producto fijo)? */
    public function ofreceCategoria(): bool
    {
        return $this->offer_category_id !== null;
    }

    /**
     * Esta regla aplicada a un producto concreto de su categoría ofrecida: una copia en
     * memoria (no se guarda) con ese producto como ofrecido. Así el resto del código
     * (precio, variantes, la API) trata igual a las dos clases de regla. Conserva el
     * id: es la regla que se aplica.
     */
    public function paraProducto(Product $producto): self
    {
        $copia = $this->replicate();
        $copia->setAttribute($this->getKeyName(), $this->getKey());
        $copia->exists = true;
        $copia->offer_product_id = $producto->getKey();
        $copia->setRelation('offerProduct', $producto);
        // Sin cambios pendientes: un save() accidental no escribe el producto en la
        // regla de categoría.
        $copia->syncOriginal();

        return $copia;
    }

    /** Precio vigente del producto ofrecido, ya con el descuento de la regla aplicado. */
    public function precioConDescuento(): float
    {
        return round($this->precioBase() * (1 - $this->discount_percent / 100), 2);
    }

    /**
     * Precio del que parte el descuento: el vigente del producto (su oferta si la tiene).
     * Misma regla que OrderService::resolveUnitPrice, para no cobrar dos criterios.
     */
    public function precioBase(): float
    {
        $product = $this->offerProduct;

        return (float) ($product->sale_price ?? $product->price);
    }

    /** "Casco AGV" o "Categoría Cascos", para el panel. */
    public function descripcionDisparador(): string
    {
        return $this->trigger_category_id !== null
            ? 'Categoría '.($this->triggerCategory?->name ?? '—')
            : ($this->triggerProduct?->name ?? '—');
    }

    public function descripcionOferta(): string
    {
        return $this->offer_category_id !== null
            ? 'Categoría '.($this->offerCategory?->name ?? '—')
            : ($this->offerProduct?->name ?? '—');
    }
}
