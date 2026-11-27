<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita "DOT" / "FMVSS 218" de las certificaciones de los cascos (decisión de Gerson,
 * 27-nov-2026): la etiqueta DOT solo la trae la versión fabricada para EE.UU. y no está
 * confirmada en los cascos que se venden. Se conserva el resto (ECE, FIM, SNELL…).
 * Cuando se confirme con la etiqueta, se vuelve a cargar desde el panel modelo por modelo.
 *
 * Ej.: "ECE 22.06 · DOT" → "ECE 22.06"; "DOT, SNELL M2015" → "SNELL M2015";
 *      "DOT FMVSS 218" → null.
 */
return new class extends Migration
{
    public static function limpiar(?string $cert): ?string
    {
        $sinDot = preg_replace('/\bFMVSS\s*218\b|\bDOT\b/i', '', (string) $cert);
        $partes = array_filter(array_map('trim', preg_split('/[·\/,|]+/u', (string) $sinDot)), fn ($p) => $p !== '');

        return $partes ? implode(' · ', $partes) : null;
    }

    public function up(): void
    {
        $productos = DB::table('products')
            ->whereNotNull('certification')
            ->where(fn ($q) => $q->where('certification', 'like', '%DOT%')->orWhere('certification', 'like', '%FMVSS%'))
            ->get(['id', 'name', 'certification']);

        foreach ($productos as $p) {
            $nueva = self::limpiar($p->certification);
            if ($nueva !== $p->certification) {
                DB::table('products')->where('id', $p->id)->update(['certification' => $nueva, 'updated_at' => now()]);
                echo "  {$p->name}: {$p->certification} → ".($nueva ?? '(sin certificación)').PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        // Dato de catálogo: se corrige desde el panel.
    }
};
