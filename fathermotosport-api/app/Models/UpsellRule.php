<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Quien compró el producto disparador, se le ofrece el producto ofrecido con descuento."
 */
class UpsellRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'trigger_product_id',
        'offer_product_id',
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

    public function offerProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'offer_product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
}
