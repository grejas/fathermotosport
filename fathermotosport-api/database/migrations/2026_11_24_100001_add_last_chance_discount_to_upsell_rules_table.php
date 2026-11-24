<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebaja de último aviso en la venta cruzada: si el cliente cierra la oferta dos
 * veces, el segundo aviso puede ofrecer un descuento mayor. Vacío = sin rebaja (el
 * aviso repite el descuento normal). La decide Gerson regla por regla en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upsell_rules', function (Blueprint $table) {
            $table->unsignedTinyInteger('last_chance_discount_percent')->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('upsell_rules', function (Blueprint $table) {
            $table->dropColumn('last_chance_discount_percent');
        });
    }
};
