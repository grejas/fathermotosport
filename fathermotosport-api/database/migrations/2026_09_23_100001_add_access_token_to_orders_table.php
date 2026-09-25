<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Token de acceso al pedido. Es lo que permite a un comprador invitado (sin cuenta)
 * ver su pedido y pagarlo: viaja en el enlace de éxito y en el retorno de PayPal.
 * Sin token y sin sesión, el pedido deja de ser visible para cualquiera que
 * conozca el UUID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->after('order_number');
        });

        // Los pedidos ya existentes también reciben su token.
        DB::table('orders')->whereNull('access_token')->orderBy('id')->select('id')->chunk(200, function ($orders) {
            foreach ($orders as $order) {
                DB::table('orders')->where('id', $order->id)->update(['access_token' => Str::random(48)]);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['access_token']);
            $table->dropColumn('access_token');
        });
    }
};
