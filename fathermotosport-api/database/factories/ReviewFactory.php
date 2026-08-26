<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 *
 * SOLO para datos de prueba en local. Genera reseñas de calificación alta
 * (4-5 estrellas) ya aprobadas, para poblar el catálogo sin pasar por la
 * moderación manual del admin.
 *
 * No garantiza por sí sola el constraint único (user_id, product_id) — al
 * generar varias reseñas evitá repetir el mismo par (ver comando de tinker
 * sugerido, que usa firstOrCreate por cada producto distinto).
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    private const TITLES = [
        'Excelente calidad',
        'Superó mis expectativas',
        'Muy cómodo y seguro',
        'Vale cada peso',
        'Perfecto ajuste',
        'Increíble relación calidad-precio',
        'Me siento muy seguro con este casco',
        'Recomendado 100%',
        'Justo lo que necesitaba',
        'Excelente compra',
    ];

    private const COMMENTS = [
        'La calidad de los materiales es excelente y el ajuste es perfecto para mi cabeza. Definitivamente lo recomiendo.',
        'Llegó rápido y muy bien empacado. Se siente muy seguro y cómodo incluso en viajes largos.',
        'Muy buena terminación y ventilación. Superó lo que esperaba por el precio.',
        'El casco es liviano y no genera ruido a alta velocidad. Muy satisfecho con la compra.',
        'Excelente atención y el producto llegó en perfectas condiciones. Se nota la calidad de la marca.',
        'Cómodo desde el primer uso, sin necesidad de "romperlo". Totalmente recomendado.',
        'La seguridad y el acabado son de primer nivel. Superó mis expectativas.',
        'Entrega rápida y el casco es exactamente como se describe. Muy conforme.',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->value('id'),
            'product_id' => Product::inRandomOrder()->value('id'),
            'rating' => fake()->numberBetween(4, 5),
            'title' => fake()->randomElement(self::TITLES),
            'comment' => fake()->randomElement(self::COMMENTS),
            'is_approved' => true,
        ];
    }
}
