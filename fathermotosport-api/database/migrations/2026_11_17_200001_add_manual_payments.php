<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de confirmación manual: transferencia SEPA (EUR), USDT (TRC20 / BEP20) y BTC.
 *
 * - store_config: los datos donde el cliente paga. No son credenciales (un IBAN o una
 *   dirección de billetera se comparten con cada comprador), por eso viven en el panel
 *   y no en el .env. Vacíos = el método no se ofrece en la web.
 * - payments.provider pasa de enum a string (como orders.payment_method en 2026_09_24):
 *   el enum rechazaba 'sepa', 'usdt_trc20', etc. al confirmar el pago.
 * - orders: la cotización fijada al crear el pedido (moneda, monto exacto a pagar, tasa
 *   usada) y hasta cuándo vale. Todo aditivo: la web anterior ignora las columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('sepa_beneficiary')->nullable();
            $table->string('sepa_iban', 34)->nullable();
            $table->string('sepa_bic', 11)->nullable();
            $table->string('sepa_bank_name')->nullable();
            $table->string('usdt_trc20_address', 64)->nullable();
            $table->string('usdt_bep20_address', 64)->nullable();
            $table->string('btc_address', 90)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 30)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_currency', 10)->nullable();
            $table->decimal('payment_amount', 20, 8)->nullable();
            $table->decimal('payment_rate', 20, 8)->nullable();
            $table->timestamp('payment_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_currency', 'payment_amount', 'payment_rate', 'payment_expires_at']);
        });

        // Vuelve al enum solo si no hay pagos manuales registrados (si no, MySQL falla).
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('provider', ['paypal', 'stripe', 'mercadopago'])->change();
        });

        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn([
                'sepa_beneficiary', 'sepa_iban', 'sepa_bic', 'sepa_bank_name',
                'usdt_trc20_address', 'usdt_bep20_address', 'btc_address',
            ]);
        });
    }
};
