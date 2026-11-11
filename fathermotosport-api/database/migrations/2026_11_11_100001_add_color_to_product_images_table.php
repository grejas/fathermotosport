<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un producto puede tener varios colores elegibles (guantes en rojo y en negro, por ejemplo):
 * cada color es un grupo de variantes (product_variants.color, que ya existía en la BD) y
 * sus fotos y videos se marcan con ese mismo nombre. NULL = el elemento sirve para todos
 * los colores (o el producto tiene un solo color, como el resto del catálogo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('color')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
