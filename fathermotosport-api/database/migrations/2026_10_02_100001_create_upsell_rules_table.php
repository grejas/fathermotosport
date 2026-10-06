<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de venta cruzada post-compra: "quien compró A, se le ofrece B con X% off".
 *
 * El id es un entero y no un uuid porque es configuración del panel, no un dato que
 * viaje en URLs públicas. Varias filas pueden compartir trigger_product_id: así un
 * mismo producto dispara hasta 3 ofertas en la pantalla de éxito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upsell_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('trigger_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('offer_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedTinyInteger('discount_percent');
            // Desempata cuando hay más ofertas que lugares en la pantalla.
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Un disparador no puede ofrecer el mismo producto dos veces.
            $table->unique(['trigger_product_id', 'offer_product_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Pedido del que salió esta venta cruzada. nullOnDelete y NO cascada: si se
            // borra el pedido original, el de upsell ya pagado no debe desaparecer.
            $table->foreignUuid('upsell_of_order_id')->nullable()->after('user_id')
                ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upsell_of_order_id');
        });

        Schema::dropIfExists('upsell_rules');
    }
};
