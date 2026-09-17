<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones recurrentes automáticas: "activa X, descansa Y, repite para siempre".
 * El scheduler genera la siguiente ocurrencia real cuando la anterior termina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_promos', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('is_active');
            // Minutos de descanso entre una ocurrencia y la siguiente.
            $table->unsignedInteger('rest_minutes')->nullable()->after('is_recurring');
            // Vincula todas las ocurrencias generadas de una misma cadena recurrente.
            $table->uuid('recurring_group_id')->nullable()->after('rest_minutes');
            $table->index('recurring_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('flash_promos', function (Blueprint $table) {
            $table->dropIndex(['recurring_group_id']);
            $table->dropColumn(['is_recurring', 'rest_minutes', 'recurring_group_id']);
        });
    }
};
