<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de venta cruzada por categoría. Una regla tiene un disparador (un producto
 * O una categoría) y una oferta (un producto O una categoría): las cuatro
 * combinaciones. Las categorías incluyen sus subcategorías y se resuelven al momento
 * de mostrar las ofertas, así un producto agregado después entra solo.
 *
 * Las reglas existentes (producto → producto) no se tocan: solo pasan a ser nullable
 * las columnas de producto, para poder guardar las de categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upsell_rules', function (Blueprint $table) {
            $table->foreignUuid('trigger_product_id')->nullable()->change();
            $table->foreignUuid('offer_product_id')->nullable()->change();

            $table->foreignId('trigger_category_id')->nullable()->after('trigger_product_id')
                ->constrained('categories')->cascadeOnDelete();
            $table->foreignId('offer_category_id')->nullable()->after('offer_product_id')
                ->constrained('categories')->cascadeOnDelete();

            // Una misma combinación no se repite. Con NULL en una de las dos columnas el
            // índice no restringe nada, así que cada uno cubre solo su combinación; la de
            // producto → producto ya existía.
            $table->unique(['trigger_category_id', 'offer_category_id'], 'upsell_rules_cat_cat_unique');
            $table->unique(['trigger_category_id', 'offer_product_id'], 'upsell_rules_cat_prod_unique');
            $table->unique(['trigger_product_id', 'offer_category_id'], 'upsell_rules_prod_cat_unique');
        });
    }

    public function down(): void
    {
        // Las reglas de categoría no caben en el esquema anterior.
        DB::table('upsell_rules')
            ->whereNotNull('trigger_category_id')
            ->orWhereNotNull('offer_category_id')
            ->delete();

        Schema::table('upsell_rules', function (Blueprint $table) {
            // Primero las FK: en MySQL el índice único que empieza por
            // trigger_category_id es el que sostiene su FK, y no deja borrarlo antes.
            $table->dropForeign(['trigger_category_id']);
            $table->dropForeign(['offer_category_id']);
            $table->dropUnique('upsell_rules_cat_cat_unique');
            $table->dropUnique('upsell_rules_cat_prod_unique');
            $table->dropUnique('upsell_rules_prod_cat_unique');
            $table->dropColumn(['trigger_category_id', 'offer_category_id']);

            $table->foreignUuid('trigger_product_id')->nullable(false)->change();
            $table->foreignUuid('offer_product_id')->nullable(false)->change();
        });
    }
};
