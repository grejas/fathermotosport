<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia country de enum('Bolivia','Brasil') a string para permitir
     * checkout internacional. No destructivo: ensancha el tipo, conserva datos.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('country', 100)->change();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('country', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('country', ['Bolivia', 'Brasil'])->change();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->enum('country', ['Bolivia', 'Brasil'])->change();
        });
    }
};
