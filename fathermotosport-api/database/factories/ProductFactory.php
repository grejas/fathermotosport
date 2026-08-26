<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 *
 * SOLO para datos de prueba en local (sqlite). No se invoca desde
 * DatabaseSeeder ni desde ningún seeder de producción — se corre manualmente,
 * ver el comando de tinker en la conversación que agregó este archivo.
 *
 * Requiere que ya existan categorías y marcas en la BD (CategorySeeder /
 * BrandSeeder de DatabaseSeeder), ya que se toman al azar de las existentes
 * en vez de crear nuevas.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $marcas = ['AGV', 'Shoei', 'Shark', 'HJC', 'LS2', 'Bell', 'Arai', 'Nolan'];
        $modelos = ['Pista', 'Corsa', 'Storm', 'Racer', 'Apex', 'Vortex', 'Nomad', 'Falcon', 'Raptor', 'Phantom'];

        $name = sprintf(
            'Casco Integral %s %s %d',
            fake()->randomElement($marcas),
            fake()->randomElement($modelos),
            fake()->numberBetween(100, 999)
        );

        $price = fake()->randomFloat(2, 80, 450);
        $conOferta = fake()->boolean(30);

        return [
            'brand_id' => Brand::inRandomOrder()->first()?->id,
            'category_id' => Category::inRandomOrder()->first()?->id,
            'sku' => 'FMS-TEST-'.Str::upper(Str::random(8)),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'short_description' => 'Casco de prueba generado para testing de paginación del catálogo.',
            'description' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'price' => $price,
            'sale_price' => $conOferta ? round($price * 0.85, 2) : null,
            'minimum_stock' => 3,
            'is_active' => true,
            'is_featured' => fake()->boolean(10),
            'is_new' => fake()->boolean(20),
            'is_popular' => fake()->boolean(15),
            'certification' => fake()->randomElement(['ECE 22.06', 'DOT', 'ECE 22.06 / DOT', null]),
        ];
    }

    /**
     * Crea 2-4 variantes con stock (sin esto ProductCard marcaría el producto
     * como agotado siempre) y una imagen de portada placeholder local.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            static::ensurePlaceholderImageExists();

            $tallas = fake()->randomElements(['S', 'M', 'L', 'XL', 'XXL'], rand(2, 4));
            foreach ($tallas as $talla) {
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'size' => $talla,
                ]);
            }

            ProductImage::factory()->create([
                'product_id' => $product->id,
                'is_primary' => true,
            ]);
        });
    }

    /**
     * Genera una vez un PNG de 1x1 en el disco 'public' para que las
     * ProductImage de prueba resuelvan a un archivo real (evita 404 y no
     * requiere agregar dominios externos a next.config.mjs).
     */
    private static function ensurePlaceholderImageExists(): void
    {
        $path = 'products/placeholder-test.png';

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
            ));
        }
    }
}
