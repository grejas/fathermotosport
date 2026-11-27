<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prueba de que el cliente aceptó Términos y Privacidad al comprar (casilla obligatoria del
 * checkout, 27-nov-2026): cuándo y qué versión. Pedidos anteriores quedan en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('locale');
            }
            if (! Schema::hasColumn('orders', 'terms_version')) {
                $table->string('terms_version', 20)->nullable()->after('terms_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};
