<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La venta cruzada existe solo mientras está abierto el modal de "pedido confirmado".
 * Estas dos marcas van en el pedido ORIGINAL y son las que el servidor consulta para
 * dejar de ofrecerla: cuándo se mostró por primera vez y cuándo la rechazó el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('upsell_offered_at')->nullable()->after('upsell_of_order_id');
            $table->timestamp('upsell_dismissed_at')->nullable()->after('upsell_offered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['upsell_offered_at', 'upsell_dismissed_at']);
        });
    }
};
