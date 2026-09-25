<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El color pasa a ser un dato del PRODUCTO (un producto = un color; otro color es
 * otro producto). product_variants.color queda en la BD sin uso, como
 * flash_promos.recurring_group_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('color')->nullable()->after('name');
        });

        // Backfill: color de la primera variante (por fecha de creación) que tenga color.
        DB::table('products')->orderBy('id')->select('id')->chunk(200, function ($products) {
            foreach ($products as $product) {
                $color = DB::table('product_variants')
                    ->where('product_id', $product->id)
                    ->whereNotNull('color')
                    ->where('color', '!=', '')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->value('color');

                if ($color !== null) {
                    DB::table('products')->where('id', $product->id)->update(['color' => trim($color)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
