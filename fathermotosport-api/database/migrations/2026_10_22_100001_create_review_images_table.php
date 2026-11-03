<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos de reseñas (hasta 3 por reseña). Nacen pendientes en el disco privado y pasan
 * al público solo cuando el admin las aprueba. Solo agrega: si se reintenta y la tabla
 * ya existe, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_images')) {
            return;
        }

        Schema::create('review_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['review_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_images');
    }
};
