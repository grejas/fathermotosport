<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'color' => fake()->randomElement(['Negro', 'Blanco', 'Rojo', 'Azul', 'Gris']),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL', 'XXL']),
            'finish' => fake()->randomElement(['Mate', 'Brillante', 'Carbono', 'Cromado']),
            'sku' => 'VAR-TEST-'.Str::upper(Str::random(8)),
            'stock' => fake()->numberBetween(5, 50),
            'is_active' => true,
        ];
    }
}
