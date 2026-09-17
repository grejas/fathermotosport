<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el sistema de "intervalo recurrente" (interval_minutes /
 * duration_minutes / anchor_at) por PROMOCIONES PROGRAMADAS con fecha/hora real
 * de inicio y fin. La tabla deja de ser un singleton: se pueden crear varias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('flash_promos');

        Schema::create('flash_promos', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('promo_text');
            // null => aplica a toda la tienda; con valor => solo a esa categoría.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            // Permite desactivar una promo puntual sin borrarla.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Acelera la consulta de "cuál está activa ahora".
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_promos');

        // Reconstruye el esquema anterior (singleton de intervalo recurrente).
        Schema::create('flash_promos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('interval_minutes')->default(30);
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->text('promo_text')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamp('anchor_at')->nullable();
            $table->timestamps();
        });
    }
};
