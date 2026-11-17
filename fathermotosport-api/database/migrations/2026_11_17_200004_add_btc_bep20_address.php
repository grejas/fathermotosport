<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dirección para cobrar Bitcoin en BNB Smart Chain (BTC · BEP20). Vacía = no se ofrece. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('btc_bep20_address', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn('btc_bep20_address');
        });
    }
};
