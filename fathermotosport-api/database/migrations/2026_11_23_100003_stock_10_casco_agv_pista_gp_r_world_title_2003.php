<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de Gerson (23-nov-2026): el Casco AGV Pista GP R World Title 2003 queda con
 * 10 unidades en cada talla (M, L, XL, XXL). Se fija el stock de cada variante en 10
 * y se deja un movimiento de inventario «adjustment» por la diferencia.
 *
 * Idempotente: si todas las tallas ya están en 10 (o el producto no existe) no hace nada.
 */
return new class extends Migration
{
    private const SLUG = 'casco-agv-pista-gp-r-world-title-2003';

    private const STOCK = 10;

    public function up(): void
    {
        $product = Product::withTrashed()->where('slug', self::SLUG)->first();
        if (! $product) {
            return;
        }

        DB::transaction(function () use ($product) {
            $variantes = $product->variants()->get();
            $antes = (int) $variantes->sum('stock');
            $diferencia = self::STOCK * $variantes->count() - $antes;
            if ($variantes->every(fn ($v) => (int) $v->stock === self::STOCK)) {
                return;
            }

            $detalle = $variantes->map(fn ($v) => ($v->size ?: $v->sku).' '.$v->stock)->implode(' · ');

            $product->variants()->update(['stock' => self::STOCK]);

            InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => null,
                'type' => 'adjustment',
                'quantity' => $diferencia,
                'reason' => 'Stock fijado en '.self::STOCK." por talla (pedido de Gerson, 23-nov-2026). Antes: {$detalle}",
            ]);
        });
    }

    public function down(): void
    {
        // No se revierte el stock automáticamente: se ajusta desde el panel.
    }
};
