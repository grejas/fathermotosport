<?php

use App\Support\ShippingCountries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Envío gratis a toda América (ShippingCountries::AMERICAS), sin límite de peso.
 * Reemplaza los 75 USD que tenían Brasil, Perú, Ecuador, Chile, Argentina, El Salvador
 * y Costa Rica, y crea la tarifa en los países que no tenían ninguna.
 *
 * Idempotente: actualiza la opción "Estándar" del país conservando sus plazos, o la crea
 * sin plazos (no se inventan: se cargan desde el panel, en Opciones de envío).
 */
return new class extends Migration
{
    private const METODO = 'Estándar';

    public function up(): void
    {
        foreach (array_keys(ShippingCountries::AMERICAS) as $codigo) {
            $campos = [
                'min_weight_kg' => 0,
                'max_weight_kg' => null,
                'price' => 0,
                'currency' => 'USD',
                'is_active' => true,
                'updated_at' => now(),
            ];

            $existente = DB::table('shipping_options')
                ->where('country_code', $codigo)
                ->where('method_name', self::METODO)
                ->first();

            if ($existente) {
                DB::table('shipping_options')->where('id', $existente->id)->update($campos);
            } else {
                DB::table('shipping_options')->insert($campos + [
                    'country_code' => $codigo,
                    'method_name' => self::METODO,
                    'estimated_days_min' => null,
                    'estimated_days_max' => null,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Cambio de tarifas de negocio: se ajusta desde el panel, no se revierte solo.
    }
};
