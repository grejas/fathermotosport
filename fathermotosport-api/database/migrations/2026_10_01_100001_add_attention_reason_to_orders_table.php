<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo por el que un pedido necesita revisión manual del admin.
 *
 * Casos actuales:
 *  - Pago confirmado pero sin stock suficiente (el dinero ya se cobró).
 *  - El país de la dirección que informó PayPal no coincide con el envío cobrado.
 *
 * Es una columna propia y no un valor de `status` porque el pedido sí está pagado y
 * en preparación: lo que hace falta es una marca ortogonal, filtrable desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('attention_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('attention_reason');
        });
    }
};
