<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de Gerson (23-nov-2026): el Casco Ruroc Atlas 4.0 Fenrir está
 * agotado en todas las tallas. Se pone el stock de sus variantes en 0 y se deja
 * un movimiento de inventario «adjustment» por lo que había, para que quede rastro
 * en el panel. Con stock 0 la ficha muestra AGOTADO y el formulario «Avísame».
 *
 * Idempotente: si ya está en 0 (o el producto no existe) no hace nada.
 */
return new class extends Migration
{
    private const SLUG = 'casco-ruroc-atlas-40-fenrir';

    public function up(): void
    {
        $product = Product::withTrashed()->where('slug', self::SLUG)->first();
        if (! $product) {
            return;
        }

        DB::transaction(function () use ($product) {
            $total = (int) $product->variants()->sum('stock');
            if ($total <= 0) {
                return;
            }

            $detalle = $product->variants()->where('stock', '>', 0)->get()
                ->map(fn ($v) => ($v->size ?: $v->sku).' '.$v->stock)
                ->implode(' · ');

            $product->variants()->update(['stock' => 0]);

            InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => null,
                'type' => 'adjustment',
                'quantity' => -$total,
                'reason' => "Agotado en todas las tallas (pedido de Gerson, 23-nov-2026). Antes: {$detalle}",
            ]);
        });
    }

    public function down(): void
    {
        // No se repone stock automáticamente: si vuelve, se carga desde el panel.
    }
};
