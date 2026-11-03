<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 *
 * `url` apunta a un PNG de 1x1 generado localmente por ProductFactory (ver
 * ensurePlaceholderImageExists()) para que el catálogo tenga imagen sin
 * depender de internet ni de dominios externos no whitelisteados en next.config.
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'url' => 'products/placeholder-test.png',
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    /** Un video de la galería (el tipo lo deduce el modelo de la extensión). */
    public function video(?string $poster = null): static
    {
        return $this->state(fn () => ['url' => 'products/videos/demo.mp4', 'poster_url' => $poster]);
    }

    public function gif(): static
    {
        return $this->state(fn () => ['url' => 'products/demo.gif']);
    }
}
