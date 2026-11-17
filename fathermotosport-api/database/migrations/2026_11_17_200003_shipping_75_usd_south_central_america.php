<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Envío de 75 USD por pedido, sin límite de peso, a Brasil, Perú, Ecuador, Chile,
 * Argentina, El Salvador y Costa Rica.
 *
 * Idempotente: si el país ya tiene la opción "Estándar" se le pone 75 USD y sin
 * límite de peso, conservando sus plazos; si no, se crea sin plazos (no se inventan:
 * se cargan desde el panel, en Opciones de envío).
 */
return new class extends Migration
{
    private const METODO = 'Estándar';

    private const PAISES = ['BR', 'PE', 'EC', 'CL', 'AR', 'SV', 'CR'];

    public function up(): void
    {
        foreach (self::PAISES as $codigo) {
            $campos = [
                'min_weight_kg' => 0,
                'max_weight_kg' => null,
                'price' => 75,
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
