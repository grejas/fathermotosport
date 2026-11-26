<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Color de visor que el cliente eligió al comprar un casco.
 * `visor_color` guarda el nombre tal cual se vendió (si después se renombra o borra
 * el color en el panel, el pedido sigue diciendo lo que pidió el cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('visor_color_id')->nullable()->after('color')
                ->constrained('visor_colors')->nullOnDelete();
            $table->string('visor_color')->nullable()->after('visor_color_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visor_color_id');
            $table->dropColumn('visor_color');
        });
    }
};
