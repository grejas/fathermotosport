<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactos captados por el popup de la web (email + WhatsApp opcional) y el cupón
 * que se le entregó a cada uno. Un email = un cupón: si vuelve, recibe el mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('whatsapp', 32)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('locale', 5)->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->unsignedTinyInteger('percent')->nullable();
            // De dónde llegó: utm_source/medium/campaign y la página donde se suscribió.
            $table->json('source')->nullable();
            $table->boolean('consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            // Hash de la IP (no la IP en claro): sirve para frenar abusos sin guardar el dato.
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_leads');
    }
};
