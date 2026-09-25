<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda en el pedido qué opción de envío eligió el cliente. El monto sigue en
 * orders.shipping; el nombre queda como copia por si la opción se edita o borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_option_id')->nullable()->after('shipping')
                ->constrained('shipping_options')->nullOnDelete();
            $table->string('shipping_method_name')->nullable()->after('shipping_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_option_id');
            $table->dropColumn('shipping_method_name');
        });
    }
};
