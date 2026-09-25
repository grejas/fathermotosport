<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory, HasUuid;

    // color y price quedan como columnas sin uso: el color y el precio son del producto.
    protected $fillable = [
        'product_id',
        'size',
        'finish',
        'sku',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * SKU de variante: {SKU_PRODUCTO}-{TALLA}, o solo {SKU_PRODUCTO} si no hay talla.
     * Lo usan el formulario de Filament y el comando products:fix-comma-sizes.
     */
    public static function skuFor(string $productSku, ?string $size): string
    {
        return filled($size) ? $productSku.'-'.Str::upper(trim($size)) : $productSku;
    }

    /**
     * Indica si un SKU sigue el patrón vigente para ese producto y talla. Acepta el
     * sufijo numérico (-2, -3…) que se agrega cuando el SKU base ya estaba ocupado.
     */
    public static function skuMatchesPattern(?string $sku, string $productSku, ?string $size): bool
    {
        $base = static::skuFor($productSku, $size);

        return $sku === $base || (bool) preg_match('/^'.preg_quote($base, '/').'-\d+$/', (string) $sku);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }
}
