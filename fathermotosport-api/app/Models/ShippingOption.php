<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ShippingOption extends Model
{
    protected $fillable = [
        'country_code',
        'method_name',
        'min_weight_kg',
        'max_weight_kg',
        'price',
        'currency',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
    ];

    protected $casts = [
        'min_weight_kg' => 'decimal:3',
        'max_weight_kg' => 'decimal:3',
        'price' => 'decimal:2',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Opciones de un país (código ISO, sin distinguir mayúsculas).
     */
    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', strtoupper(trim($countryCode)));
    }

    /**
     * Opciones cuyo rango de peso contiene $weightKg. El rango es
     * [min_weight_kg, max_weight_kg]; max_weight_kg null = sin límite superior.
     */
    public function scopeForWeight(Builder $query, float $weightKg): Builder
    {
        return $query->where('min_weight_kg', '<=', $weightKg)
            ->where(fn (Builder $q) => $q->whereNull('max_weight_kg')->orWhere('max_weight_kg', '>=', $weightKg));
    }

    /**
     * Opciones vigentes para un país y un peso, una por método y ordenadas por precio.
     * Si un método tiene varias filas que aplican, gana la del rango más ajustado
     * (el min_weight_kg más alto) y, a igualdad, la más barata.
     *
     * @return Collection<int, ShippingOption>
     */
    public static function availableFor(string $countryCode, float $weightKg): Collection
    {
        $options = static::query()
            ->active()
            ->forCountry($countryCode)
            ->forWeight($weightKg)
            ->orderByDesc('min_weight_kg')
            ->orderBy('price')
            ->get()
            ->unique('method_name')
            ->sortBy([['price', 'asc'], ['estimated_days_max', 'asc']])
            ->values();

        return new Collection($options->all());
    }

    /**
     * Opción concreta elegida por el cliente, validando que siga vigente para ese
     * país y peso (el precio nunca se toma de lo que manda el navegador).
     */
    public static function findAvailable(int $id, string $countryCode, float $weightKg): ?self
    {
        return static::availableFor($countryCode, $weightKg)->firstWhere('id', $id);
    }
}
