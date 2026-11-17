<?php

use App\Support\ShippingCountries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Envío gratis a toda Europa (los 20 países de ShippingCountries::EUROPE), México,
 * Estados Unidos y Guatemala, sin límite de peso.
 *
 * Antes las tarifas existentes iban de 1 a 2 kg: un carrito con dos cascos (o un
 * producto sin peso cargado por debajo de 1 kg) quedaba "sin tarifa para ese país".
 *
 * Idempotente: si el país ya tiene la opción "Estándar", se la deja gratis y sin
 * límite de peso conservando sus plazos; si no, se crea. Los plazos de las nuevas
 * quedan vacíos (no se inventan): se cargan desde el panel, en Opciones de envío.
 */
return new class extends Migration
{
    private const METODO = 'Estándar';

    public function up(): void
    {
        $paises = array_merge(array_keys(ShippingCountries::EUROPE), ['MX', 'US', 'GT']);

        foreach ($paises as $codigo) {
            $existente = DB::table('shipping_options')
                ->where('country_code', $codigo)
                ->where('method_name', self::METODO)
                ->first();

            $campos = [
                'min_weight_kg' => 0,
                'max_weight_kg' => null,
                'price' => 0,
                'currency' => 'USD',
                'is_active' => true,
                'updated_at' => now(),
            ];

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
        // Es un cambio de datos de negocio: no se revierte solo. Las tarifas se
        // ajustan desde el panel.
    }
};
