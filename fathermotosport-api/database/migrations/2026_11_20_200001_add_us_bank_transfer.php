<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferencia bancaria en EE.UU. (ACH / wire, USD) a la cuenta Mercury de FATHERSON IMP LLC.
 *
 * Como el IBAN de SEPA, son datos que se comparten con cada comprador que elige este
 * método (no credenciales): viven en el panel, no en el .env. Vacíos = el método no se
 * ofrece en la web. Aditivo: la web anterior ignora las columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->string('usbank_beneficiary')->nullable();
            $table->string('usbank_beneficiary_address')->nullable();
            $table->string('usbank_bank_name')->nullable();
            $table->string('usbank_bank_address')->nullable();
            $table->string('usbank_account_number', 17)->nullable();
            $table->string('usbank_routing_number', 9)->nullable();
            $table->string('usbank_account_type', 20)->nullable();
            $table->string('usbank_swift', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_config', function (Blueprint $table) {
            $table->dropColumn([
                'usbank_beneficiary', 'usbank_beneficiary_address', 'usbank_bank_name', 'usbank_bank_address',
                'usbank_account_number', 'usbank_routing_number', 'usbank_account_type', 'usbank_swift',
            ]);
        });
    }
};
