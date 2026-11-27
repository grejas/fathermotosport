<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita certificaciones que no corresponden al tipo de producto (auditoría legal, 27-nov-2026):
 * ECE 22.0x y DOT son normas de cascos, y una chaqueta o un guante no las puede tener;
 * "ISO 9001" es una certificación de gestión de una empresa, no de un producto.
 * Anunciarlas es publicidad engañosa (CDC art. 37, FTC Act §5, Directiva 2005/29).
 *
 * Solo se vacía el campo (la ficha deja de mostrar la insignia). La certificación real de
 * cada producto (EN 17092 para ropa, EN 13594 para guantes, EN 13634 para botas) se carga
 * desde el panel cuando se confirme con la etiqueta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cascos = DB::table('categories')->where('slug', 'cascos')->value('id');

        $productos = DB::table('products')
            ->whereNotNull('certification')
            ->when($cascos, fn ($q) => $q->where(fn ($w) => $w->where('category_id', '!=', $cascos)->orWhereNull('category_id')))
            ->get(['id', 'name', 'certification']);

        foreach ($productos as $p) {
            if (preg_match('/ECE\s*22|DOT|FMVSS|SNELL|ISO\s*9001/i', (string) $p->certification)) {
                DB::table('products')->where('id', $p->id)->update(['certification' => null, 'updated_at' => now()]);
                echo "  certificación quitada: {$p->name} ({$p->certification})".PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        // Dato de catálogo: se corrige desde el panel.
    }
};
