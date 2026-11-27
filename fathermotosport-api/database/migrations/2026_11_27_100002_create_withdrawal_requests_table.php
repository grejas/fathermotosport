<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de desistimiento ("Desistir de una compra"). Registro de lo que el cliente
 * declaró y cuándo, como prueba ante una autoridad de consumo (art. 11a Directiva 2011/83,
 * CDC art. 49). No se borran: el equipo solo las marca como gestionadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un primer intento (27-nov-2026) dejó la tabla creada sin la clave foránea (MySQL no
        // revierte el CREATE). Si quedó vacía, se rehace.
        if (Schema::hasTable('withdrawal_requests')) {
            if (DB::table('withdrawal_requests')->exists()) {
                throw new RuntimeException('withdrawal_requests ya tiene datos: revisar a mano antes de migrar.');
            }
            Schema::drop('withdrawal_requests');
        }

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40);
            // Pedido encontrado con ese número y ese email; null = no coincide (se revisa a mano).
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->text('items')->nullable();
            $table->text('reason')->nullable();
            $table->string('locale', 2)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
