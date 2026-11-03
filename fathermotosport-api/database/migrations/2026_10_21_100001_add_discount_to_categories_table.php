<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento por categoría: porcentaje, interruptor y fechas opcionales, más los
 * productos excluidos de ese descuento.
 *
 * Aditiva: toda categoría queda con el descuento apagado, así nada cambia de precio
 * hasta que se active en el panel. No toca productos ni pedidos.
 */
return new class extends Migration
{
    /** Se puede volver a correr: cada columna y la tabla se crean solo si faltan. */
    public function up(): void
    {
        $columnas = [
            'discount_percent' => fn (Blueprint $t) => $t->decimal('discount_percent', 4, 2)->nullable()->after('size_options'),
            'discount_active' => fn (Blueprint $t) => $t->boolean('discount_active')->default(false)->after('discount_percent'),
            'discount_starts_at' => fn (Blueprint $t) => $t->timestamp('discount_starts_at')->nullable()->after('discount_active'),
            'discount_ends_at' => fn (Blueprint $t) => $t->timestamp('discount_ends_at')->nullable()->after('discount_starts_at'),
        ];

        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn('categories', $columna)) {
                Schema::table('categories', $definir);
            }
        }

        if (! Schema::hasTable('category_discount_exclusions')) {
            Schema::create('category_discount_exclusions', function (Blueprint $table) {
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->primary(['category_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_discount_exclusions');

        $columnas = array_values(array_filter(
            ['discount_percent', 'discount_active', 'discount_starts_at', 'discount_ends_at'],
            fn (string $columna) => Schema::hasColumn('categories', $columna)
        ));

        if ($columnas !== []) {
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn($columnas));
        }
    }
};
