<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Avísame cuando esté disponible»: clientes interesados en un producto agotado
 * (y la talla que buscan). El equipo los avisa a mano y marca «avisado» en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 32)->nullable();
            $table->string('email', 190);
            $table->string('whatsapp', 32)->nullable();
            $table->string('locale', 5)->nullable();
            // Hash de la IP (no la IP en claro): sirve para frenar abusos sin guardar el dato.
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamp('notified_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['product_id', 'size', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
