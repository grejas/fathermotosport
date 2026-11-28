<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayPal como pago manual (mientras no esté el PayPal Business de la LLC): correo de la
 * cuenta que recibe, link PayPal.me opcional, nombre que ve el cliente y FEE (%).
 * Sin correo cargado, el método no se ofrece.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('pp_manual_email')->nullable();
            $table->string('pp_manual_link')->nullable();
            $table->string('pp_manual_name', 100)->nullable();
            $table->decimal('pp_manual_fee_percent', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn(['pp_manual_email', 'pp_manual_link', 'pp_manual_name', 'pp_manual_fee_percent']);
        });
    }
};
