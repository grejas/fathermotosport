<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_visor_colors', function (Blueprint $table) {
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('visor_color_id')->constrained('visor_colors')->cascadeOnDelete();

            $table->primary(['product_id', 'visor_color_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_visor_colors');
    }
};
