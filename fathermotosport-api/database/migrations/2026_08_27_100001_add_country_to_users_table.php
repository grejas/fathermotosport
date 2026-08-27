<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Código ISO-3166-1 alfa-2 (ej. "BO", "ES"). Nullable a nivel de BD
            // para no romper usuarios existentes; requerido a nivel de
            // validación solo para registros nuevos (VerifyAndRegisterRequest).
            $table->string('country', 2)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
