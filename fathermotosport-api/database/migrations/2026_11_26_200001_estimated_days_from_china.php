<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plazos de entrega reales: todo sale desde China.
 *
 * estimated_days_min / estimated_days_max pasan a ser DÍAS HÁBILES TOTALES:
 * procesamiento (2-4: compra al proveedor y despacho) + tránsito por región.
 * El checkout los convierte en un rango de fechas ("Llega entre el … y el …").
 *
 * Antes había plazos irreales (1-2 días a Bolivia, 3-5 a EE.UU., 5-7 a Brasil) y
 * países sin plazo (España). Pedido de Gerson, 26-nov-2026, auditoría legal:
 * un plazo prometido que no se cumple es publicidad engañosa (FTC, CDC, UE).
 *
 * Idempotente: solo actualiza plazos de filas existentes; no toca precios ni crea
 * opciones. Se pueden seguir ajustando desde el panel → Opciones de envío.
 */
return new class extends Migration
{
    /** Tránsito Estándar (días hábiles) por grupo de países. */
    private const ESTANDAR = [
        [12, 25, ['US', 'ES', 'PT', 'FR', 'DE', 'IT', 'NL', 'BE', 'AT', 'IE', 'GB']],
        [15, 30, ['CA', 'MX', 'DK', 'SE', 'FI', 'NO', 'CH', 'PL', 'CZ', 'RO', 'HU', 'GR']],
        [20, 35, ['CL', 'CO', 'PE', 'UY', 'EC', 'CR', 'PA', 'DO']],
        [20, 40, ['BR']],
        [25, 45, ['AR', 'BO', 'PY', 'VE', 'GT', 'HN', 'SV', 'NI']],
    ];

    /** Tránsito Express / DHL (días hábiles) por grupo de países. */
    private const EXPRESS = [
        [3, 7, ['US', 'ES', 'PT', 'FR', 'DE', 'IT', 'NL', 'BE', 'AT', 'IE', 'GB']],
        [4, 8, ['CA', 'MX', 'DK', 'SE', 'FI', 'NO', 'CH', 'PL', 'CZ', 'RO', 'HU', 'GR']],
        [4, 8, ['CL', 'CO', 'PE', 'UY', 'EC', 'CR', 'PA', 'DO']],
        [5, 10, ['BR', 'AR', 'BO', 'PY', 'VE', 'GT', 'HN', 'SV', 'NI']],
    ];

    private const PROCESO_MIN = 2;
    private const PROCESO_MAX = 4;

    public function up(): void
    {
        foreach (DB::table('shipping_options')->get(['id', 'country_code', 'method_name']) as $opcion) {
            $nombre = mb_strtolower((string) $opcion->method_name);
            $esExpress = str_contains($nombre, 'express') || str_contains($nombre, 'dhl');
            $tabla = $esExpress ? self::EXPRESS : self::ESTANDAR;

            foreach ($tabla as [$min, $max, $paises]) {
                if (in_array(strtoupper($opcion->country_code), $paises, true)) {
                    DB::table('shipping_options')->where('id', $opcion->id)->update([
                        'estimated_days_min' => self::PROCESO_MIN + $min,
                        'estimated_days_max' => self::PROCESO_MAX + $max,
                        'updated_at' => now(),
                    ]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        // Plazos de negocio: se ajustan desde el panel, no se revierten solos.
    }
};
