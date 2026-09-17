<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_promos', function (Blueprint $table) {
            // null => la promo aplica a toda la tienda; con valor => solo a esa categoría.
            $table->foreignId('category_id')
                ->nullable()
                ->after('is_active')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flash_promos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
