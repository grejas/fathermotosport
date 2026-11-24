<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos campos opcionales en las reglas de venta cruzada:
 *
 * - Rebaja de último aviso: si el cliente cierra la oferta dos veces, el segundo aviso
 *   puede ofrecer un descuento mayor. Vacío = sin rebaja. La decide Gerson en el panel.
 * - Grupo: de las reglas de un mismo grupo (por ejemplo las 6 riñoneras) se muestra una
 *   sola por pedido, rotando entre clientes, para que no ocupen todos los lugares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upsell_rules', function (Blueprint $table) {
            $table->unsignedTinyInteger('last_chance_discount_percent')->nullable()->after('discount_percent');
            $table->string('upsell_group', 40)->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('upsell_rules', function (Blueprint $table) {
            $table->dropColumn(['last_chance_discount_percent', 'upsell_group']);
        });
    }
};
