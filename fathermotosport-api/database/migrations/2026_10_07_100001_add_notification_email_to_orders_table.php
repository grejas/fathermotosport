<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email que el cliente escribió en la tienda para que le avisemos de su pedido (hoy,
 * el de PayPal Express). Se guarda aparte de guest_email porque en Express ese campo lo
 * llena PayPal al volver, con email_verificado_por = 'paypal', y es el que habilita
 * vincular pedidos y crear la cuenta: el escrito no prueba que la casilla sea de quien
 * compra, así que nunca lo reemplaza.
 *
 * Los correos al cliente usan notification_email y, si es null (todos los pedidos
 * anteriores), el email de siempre: guest_email o el de la cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('notification_email')->nullable()->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('notification_email');
        });
    }
};
