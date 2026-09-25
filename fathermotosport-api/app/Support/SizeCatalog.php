<?php

namespace App\Support;

use App\Models\Category;

/**
 * Tallas válidas por categoría (por slug). Las categorías sin entrada, o los productos
 * sin categoría, no llevan talla: la variante se guarda con size = NULL.
 */
class SizeCatalog
{
    public const LETTERS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    /** slug de categoría => tipo de talla */
    private const BY_CATEGORY = [
        'cascos' => 'letters',
        'guantes' => 'letters',
        'chamarras' => 'letters',
        'botas' => 'boots',
    ];

    /** Tallas EU enteras de botas: 36 a 47. */
    public static function bootSizes(): array
    {
        return array_map('strval', range(36, 47));
    }

    /**
     * Tallas de una categoría. Si la categoría no tiene mapeo propio, hereda el de
     * su categoría padre. [] = la categoría no usa talla.
     */
    public static function forCategoryId(int|string|null $categoryId): array
    {
        $seen = [];

        while ($categoryId && ! in_array($categoryId, $seen, true)) {
            $seen[] = $categoryId;
            $category = Category::find($categoryId, ['id', 'slug', 'parent_id']);

            if (! $category) {
                break;
            }
            if (isset(self::BY_CATEGORY[$category->slug])) {
                return self::sizesOfType(self::BY_CATEGORY[$category->slug]);
            }

            $categoryId = $category->parent_id;
        }

        return [];
    }

    /** Todas las tallas válidas de cualquier categoría (letras + botas). */
    public static function all(): array
    {
        return array_merge(self::LETTERS, self::bootSizes());
    }

    private static function sizesOfType(string $type): array
    {
        return $type === 'boots' ? self::bootSizes() : self::LETTERS;
    }
}
