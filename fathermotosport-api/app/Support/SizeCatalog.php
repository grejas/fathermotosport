<?php

namespace App\Support;

use App\Models\Category;

/**
 * Tallas válidas por categoría, según su tipo de stock (categories.size_type y
 * size_options), que se configura en el panel. Las categorías sin tallas, o los
 * productos sin categoría, guardan la variante con size = NULL.
 */
class SizeCatalog
{
    /**
     * Orden canónico de las tallas por letra: el mismo que usa la web para mostrarlas
     * (lib/utils/sizes.ts). Son las que ofrece el panel en "Letras".
     */
    public const LETTER_ORDER = ['XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'];

    /** Pasos permitidos en "Números": tallas enteras o medias tallas. */
    public const NUMERIC_STEPS = [1, 0.5];

    /** Tope de tallas que genera un rango numérico (evita un "1 a 9999" por error). */
    public const MAX_NUMERIC_SIZES = 100;

    /**
     * Tallas de una categoría. Si su tipo es 'inherit', hereda el de su categoría
     * padre; una raíz en 'inherit' no usa talla. [] = la categoría no usa talla.
     */
    public static function forCategoryId(int|string|null $categoryId): array
    {
        $seen = [];

        while ($categoryId && ! in_array($categoryId, $seen, true)) {
            $seen[] = $categoryId;
            $category = Category::find($categoryId, ['id', 'parent_id', 'size_type', 'size_options']);

            if (! $category) {
                break;
            }
            if (($category->size_type ?? Category::SIZE_INHERIT) !== Category::SIZE_INHERIT) {
                return self::sizesOf($category->size_type, $category->size_options);
            }

            $categoryId = $category->parent_id;
        }

        return [];
    }

    /**
     * Tallas de un tipo de stock con sus opciones. 'inherit' y 'none' no tienen tallas
     * propias. Letras: en el orden canónico, sin importar cómo se marcaron. Números: de
     * menor a mayor. Lista propia: en el orden en que se escribió.
     */
    public static function sizesOf(?string $type, ?array $options): array
    {
        $options ??= [];

        return match ($type) {
            Category::SIZE_LETTERS => array_values(array_intersect(self::LETTER_ORDER, (array) ($options['sizes'] ?? []))),
            Category::SIZE_NUMERIC => self::numericSizes($options['from'] ?? null, $options['to'] ?? null, $options['step'] ?? null),
            Category::SIZE_CUSTOM => self::cleanList((array) ($options['sizes'] ?? [])),
            default => [],
        };
    }

    /**
     * Tallas de un rango numérico, como texto: "36", "36.5"… (medias tallas con punto).
     * Un rango inválido (desde > hasta, paso no permitido o más de MAX_NUMERIC_SIZES
     * tallas) devuelve [].
     */
    public static function numericSizes(mixed $from, mixed $to, mixed $step): array
    {
        if (! is_numeric($from) || ! is_numeric($to) || ! is_numeric($step)) {
            return [];
        }

        $from = (float) $from;
        $to = (float) $to;
        $step = (float) $step;

        if (! in_array($step, self::NUMERIC_STEPS, false) || $from > $to) {
            return [];
        }

        // Por índice y no sumando el paso, para no acumular error de coma flotante.
        $count = (int) floor(($to - $from) / $step + 1e-9) + 1;

        if ($count > self::MAX_NUMERIC_SIZES) {
            return [];
        }

        $sizes = [];
        for ($i = 0; $i < $count; $i++) {
            $sizes[] = self::formatNumber($from + $i * $step);
        }

        return $sizes;
    }

    /** Todas las tallas configuradas en alguna categoría (sin repetir). */
    public static function all(): array
    {
        $sizes = [];

        Category::query()
            ->whereNotIn('size_type', [Category::SIZE_INHERIT, Category::SIZE_NONE])
            ->get(['id', 'size_type', 'size_options'])
            ->each(function (Category $category) use (&$sizes) {
                array_push($sizes, ...self::sizesOf($category->size_type, $category->size_options));
            });

        return array_values(array_unique($sizes));
    }

    /** Lista escrita a mano: sin espacios de más, sin vacíos ni repetidos. */
    public static function cleanList(array $sizes): array
    {
        $limpias = array_filter(
            array_map(fn ($size) => trim((string) $size), $sizes),
            fn (string $size) => $size !== ''
        );

        return array_values(array_unique($limpias));
    }

    private static function formatNumber(float $value): string
    {
        return $value == floor($value)
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
