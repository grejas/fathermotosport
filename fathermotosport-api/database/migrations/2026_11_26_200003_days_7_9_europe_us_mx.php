<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plazo 7-9 días hábiles totales (opción Estándar) a toda Europa, EE.UU. y México,
 * según entregas reales comprobadas por Gerson (pedido del 26-nov-2026).
 * Antes: 7-22 (EE.UU./Europa occidental), 10-27 (resto de Europa), 7-12 (México).
 * Latinoamérica y Canadá no cambian. Idempotente; se ajusta desde el panel.
 */
return new class extends Migration
{
    private const PAISES = [
        'US', 'MX',
        'ES', 'PT', 'FR', 'DE', 'IT', 'NL', 'BE', 'AT', 'IE', 'GB',
        'DK', 'SE', 'FI', 'NO', 'CH', 'PL', 'CZ', 'RO', 'HU', 'GR',
    ];

    public function up(): void
    {
        DB::table('shipping_options')
            ->whereIn('country_code', self::PAISES)
            ->where('method_name', 'Estándar')
            ->update([
                'estimated_days_min' => 7,
                'estimated_days_max' => 9,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Plazos de negocio: se ajustan desde el panel, no se revierten solos.
    }
};
