<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos automáticos del popup: cuándo salió el de bienvenida (con el cupón), cuándo el
 * recordatorio antes de vencer, y la baja propia del contacto (no tiene cuenta de cliente).
 * Aditiva: no toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('popup_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('popup_leads', 'welcome_sent_at')) {
                $table->timestamp('welcome_sent_at')->nullable();
            }
            if (! Schema::hasColumn('popup_leads', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable();
            }
            if (! Schema::hasColumn('popup_leads', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('popup_leads', function (Blueprint $table) {
            $table->dropColumn(['welcome_sent_at', 'reminder_sent_at', 'unsubscribed_at']);
        });
    }
};
