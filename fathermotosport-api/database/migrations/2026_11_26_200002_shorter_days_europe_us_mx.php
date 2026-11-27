<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plazos más cortos (días hábiles totales) donde Gerson comprobó entregas reales,
 * 26-nov-2026: a Europa llega en 7-10 días y a México en 7-12 días hábiles.
 *
 * - EE.UU. y Europa occidental (incl. Reino Unido): 7-22 (antes 14-29).
 * - Resto de Europa (nórdicos, este, Suiza, Noruega, Grecia): 10-27 (antes 17-34).
 * - México: 7-12 (antes 17-34).
 * Solo la opción "Estándar". Latinoamérica y Canadá no cambian.
 * Idempotente; se sigue ajustando desde el panel → Opciones de envío.
 */
return new class extends Migration
{
    private const PLAZOS = [
        [7, 22, ['US', 'ES', 'PT', 'FR', 'DE', 'IT', 'NL', 'BE', 'AT', 'IE', 'GB']],
        [10, 27, ['DK', 'SE', 'FI', 'NO', 'CH', 'PL', 'CZ', 'RO', 'HU', 'GR']],
        [7, 12, ['MX']],
    ];

    public function up(): void
    {
        foreach (self::PLAZOS as [$min, $max, $paises]) {
            DB::table('shipping_options')
                ->whereIn('country_code', $paises)
                ->where('method_name', 'Estándar')
                ->update([
                    'estimated_days_min' => $min,
                    'estimated_days_max' => $max,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Plazos de negocio: se ajustan desde el panel, no se revierten solos.
    }
};
