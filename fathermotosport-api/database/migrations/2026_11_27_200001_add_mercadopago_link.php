<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mercado Pago México por link de cobro (cuenta de un intermediario): enlace de monto
 * abierto y nombre del titular que ve el cliente. Vacío = no se ofrece.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('mp_link_url')->nullable();
            $table->string('mp_link_name', 100)->nullable();
            // Comisión del intermediario, en %, que se suma al monto en MXN.
            $table->decimal('mp_link_fee_percent', 5, 2)->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn(['mp_link_url', 'mp_link_name', 'mp_link_fee_percent']);
        });
    }
};
