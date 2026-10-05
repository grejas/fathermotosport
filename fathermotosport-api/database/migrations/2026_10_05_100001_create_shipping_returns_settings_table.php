<?php

use Database\Seeders\ShippingReturnsSettingsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_returns_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('damage_report_hours')->default(48);

            // Un JSON por campo: {"es": ..., "pt": ..., "en": ...}
            $table->json('badge_returns')->nullable();
            $table->json('summary')->nullable();
            $table->json('page_title')->nullable();
            $table->json('page_body')->nullable(); // HTML del RichEditor

            $table->timestamps();
        });

        // La fila inicial se crea acá (y no solo en el seeder) para que el panel ya
        // muestre los textos vigentes tras un `migrate` en producción.
        $row = ['id' => 1, 'created_at' => now(), 'updated_at' => now()];
        foreach (ShippingReturnsSettingsSeeder::defaults() as $column => $value) {
            $row[$column] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }
        DB::table('shipping_returns_settings')->insert($row);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_returns_settings');
    }
};
