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

    // price queda como columna sin uso: el precio es del producto. El color de la variante
    // solo se usa en los productos con varios colores elegibles (ver skuFor); en el resto
    // es NULL y el color es el del producto.
    protected $fillable = [
        'product_id',
        'color',
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
     * SKU de variante: {SKU_PRODUCTO}-{TALLA}, o solo {SKU_PRODUCTO} si no hay talla. Si la
     * variante tiene color: {SKU_PRODUCTO}-{COLOR}-{TALLA} (el color sin tildes ni espacios).
     * Lo usan el formulario de Filament y el comando products:fix-comma-sizes.
     */
    public static function skuFor(string $productSku, ?string $size, ?string $color = null): string
    {
        $sku = $productSku;

        if (filled($color)) {
            $sku .= '-'.Str::upper(Str::slug($color));
        }

        return filled($size) ? $sku.'-'.Str::upper(trim($size)) : $sku;
    }

    /**
     * Indica si un SKU sigue el patrón vigente para ese producto y talla. Acepta el
     * sufijo numérico (-2, -3…) que se agrega cuando el SKU base ya estaba ocupado.
     */
    public static function skuMatchesPattern(?string $sku, string $productSku, ?string $size, ?string $color = null): bool
    {
        $base = static::skuFor($productSku, $size, $color);

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
