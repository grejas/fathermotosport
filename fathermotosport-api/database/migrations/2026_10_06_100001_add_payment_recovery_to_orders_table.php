<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo de recuperación de pago: un único aviso al cliente que llegó al checkout y no
 * terminó de pagar.
 *
 * - payment_failed_at: la pasarela rechazó el cobro (tarjeta o captura de PayPal). Solo
 *   adelanta el aviso; no cambia el estado de pago ni cancela nada.
 * - recovery_email_sent_at: el aviso ya salió. Es lo que impide mandarlo dos veces.
 * - locale: idioma en que el cliente hizo el pedido (es, pt, en), para escribirle en el
 *   mismo. Los pedidos anteriores quedan en null y reciben el correo en español.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('country');
            $table->timestamp('payment_failed_at')->nullable()->after('payment_status');
            $table->timestamp('recovery_email_sent_at')->nullable()->after('payment_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['locale', 'payment_failed_at', 'recovery_email_sent_at']);
        });
    }
};
