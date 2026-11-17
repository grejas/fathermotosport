<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Binance Pay: enlace del QR de cobro y nombre de la cuenta. Vacío = no se ofrece. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('binance_pay_url')->nullable();
            $table->string('binance_pay_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn(['binance_pay_url', 'binance_pay_name']);
        });
    }
};
