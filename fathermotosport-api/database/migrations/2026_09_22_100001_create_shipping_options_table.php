<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones de envío por país y rango de peso. Fase 1: el admin carga los precios
 * manualmente. Una integración automática (AliExpress/DHL) podrá alimentar esta
 * misma tabla más adelante sin cambiar el resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_options', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2);
            // Nombre visible para el cliente: "Estándar", "Express DHL"…
            $table->string('method_name');
            $table->decimal('min_weight_kg', 8, 3)->default(0);
            // null = sin límite superior ("de min_weight_kg en adelante").
            $table->decimal('max_weight_kg', 8, 3)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_options');
    }
};
