<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('provider', ['paypal', 'stripe', 'mercadopago']);
            $table->string('transaction_id')->unique();
            $table->enum('currency', ['USD', 'BOB', 'BRL'])->default('USD');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->enum('status', ['pending', 'approved', 'failed', 'refunded'])->default('pending');
            $table->json('gateway_response')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
