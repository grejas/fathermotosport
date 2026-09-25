<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shipping_methods queda solo como catálogo de transportistas (DHL, FedEx…) para
 * usar al cargar el tracking de un envío. El precio, el país y los plazos ahora
 * viven en shipping_options, que es lo que ve el cliente en el checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn(['price', 'country', 'delivery_days_min', 'delivery_days_max']);
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('country')->default('');
            $table->integer('delivery_days_min')->default(0);
            $table->integer('delivery_days_max')->default(0);
        });
    }
};
