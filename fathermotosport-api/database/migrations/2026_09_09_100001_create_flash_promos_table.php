<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_promos', function (Blueprint $table) {
            $table->id();
            // Cada cuántos minutos se repite el ciclo (soporta valores grandes, ej. 2880 = cada 2 días).
            $table->unsignedInteger('interval_minutes')->default(30);
            // Cuánto dura activa cada ventana (ej. 30, o 1440 = 1 día completo).
            $table->unsignedInteger('duration_minutes')->default(30);
            // Texto libre de la promoción.
            $table->text('promo_text')->nullable();
            // Toggle general para prender/apagar toda la función.
            $table->boolean('is_active')->default(false);
            // Punto de referencia fijo (medianoche del día en que se guardó la config).
            $table->timestamp('anchor_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_promos');
    }
};
