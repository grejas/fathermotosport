<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasUuid;

    protected $fillable = [
        'product_id',
        'url',
        'thumbnail_url',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    // NOTA: no se define un accessor sobre `url` para no sombrear la columna cruda
    // que Filament (FileUpload) necesita en formato relativo. La conversión a URL
    // pública absoluta se hace en la capa de presentación (API Resources) con el
    // helper estático publicUrl().

    /**
     * Convierte una ruta relativa del disco público en URL absoluta.
     * Idempotente: si ya es una URL (http) la devuelve sin cambios.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
