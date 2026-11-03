<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las credenciales de pago ya no se editan en el panel: PayPal, Stripe y MercadoPago
 * las leen solo del .env del servidor. Se borra la columna store_config.payment_keys,
 * que nadie leía y guardaba las llaves en texto plano.
 *
 * Se puede volver a correr: solo borra si la columna existe. down() la recrea vacía
 * (las llaves que hubiera no vuelven).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tieneColumna()) {
            Schema::table('store_config', fn (Blueprint $table) => $table->dropColumn('payment_keys'));
        }
    }

    public function down(): void
    {
        if (! $this->tieneColumna()) {
            Schema::table('store_config', fn (Blueprint $table) => $table->json('payment_keys')->nullable()->after('maintenance_mode'));
        }
    }

    /** Consulta real también con --pretend, para que muestre el SQL que va a correr. */
    private function tieneColumna(): bool
    {
        return Schema::getConnection()->withoutPretending(
            fn () => Schema::hasTable('store_config') && Schema::hasColumn('store_config', 'payment_keys')
        );
    }
};
