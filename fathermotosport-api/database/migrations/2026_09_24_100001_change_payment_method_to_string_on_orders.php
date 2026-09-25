<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_method era enum('paypal','stripe','mercadopago'): en MySQL rechaza cualquier
 * valor fuera de esa lista, así que agregar un método de pago obligaba a migrar la
 * columna cada vez. Pasa a string; los valores admitidos se validan en
 * StoreOrderRequest, que es donde se ve de un vistazo cuáles están habilitados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['paypal', 'stripe', 'mercadopago'])->nullable()->change();
        });
    }
};
