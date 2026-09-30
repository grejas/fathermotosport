<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde salió el email del pedido de invitado.
 *
 * 'paypal' = lo informó PayPal al pagar, así que quien pagó controla esa casilla.
 * null = lo escribió alguien en el checkout, y no está verificado.
 *
 * Solo con 'paypal' se permite que, al crear la cuenta desde un pedido, se vinculen
 * TAMBIÉN los otros pedidos de invitado con ese mismo email: si no, cualquiera podría
 * comprar poniendo el correo de otra persona y quedarse con su historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('email_verificado_por', 20)->nullable()->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('email_verificado_por');
        });
    }
};
