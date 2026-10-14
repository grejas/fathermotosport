<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de stock por categoría, editable desde el panel (antes era una lista fija de
 * slugs en SizeCatalog).
 *
 * Aditiva: toda categoría queda en 'inherit' (hereda del padre; en una raíz, sin tallas),
 * y las que hoy usan talla reciben su lista actual, así nada cambia de comportamiento.
 * El paso de datos son UPDATE por slug: un slug que no exista actualiza 0 filas.
 */
return new class extends Migration
{
    /** size_type => slugs de producción que lo usan hoy y su size_options. */
    private const TIPOS_ACTUALES = [
        'letters' => [
            'slugs' => ['cascos', 'guantes', 'chamarras'],
            'options' => ['sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
        ],
        'numeric' => [
            'slugs' => ['botas'],
            'options' => ['from' => 36, 'to' => 47, 'step' => 1],
        ],
    ];

    /**
     * Se puede volver a correr: cada columna se agrega solo si falta, y el paso de datos
     * solo toca categorías que siguen en 'inherit' (nunca pisa lo configurado en el panel).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'size_type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('size_type')->default('inherit')->after('sort_order');
            });
        }

        if (! Schema::hasColumn('categories', 'size_options')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->json('size_options')->nullable()->after('size_type');
            });
        }

        foreach (self::TIPOS_ACTUALES as $tipo => $config) {
            DB::table('categories')
                ->whereIn('slug', $config['slugs'])
                ->where('size_type', 'inherit')
                ->update([
                    'size_type' => $tipo,
                    'size_options' => json_encode($config['options']),
                ]);
        }
    }

    public function down(): void
    {
        $columnas = array_values(array_filter(
            ['size_type', 'size_options'],
            fn (string $columna) => Schema::hasColumn('categories', $columna)
        ));

        if ($columnas !== []) {
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn($columnas));
        }
    }
};
