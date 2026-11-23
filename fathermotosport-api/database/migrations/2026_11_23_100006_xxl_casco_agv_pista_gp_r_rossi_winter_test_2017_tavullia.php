<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de Gerson (23-nov-2026): el Casco AGV Pista GP R Rossi Winter Test 2017
 * Tavullia también está disponible en talla XXL, con 5 unidades. Se crea la variante
 * (SKU {SKU_PRODUCTO}-XXL) y se deja un movimiento de inventario «in».
 *
 * Idempotente: si la variante XXL ya existe no se toca (ni su stock).
 */
return new class extends Migration
{
    private const SLUG = 'casco-agv-pista-gp-r-rossi-winter-test-2017-tavullia';

    private const TALLA = 'XXL';

    private const STOCK = 5;

    public function up(): void
    {
        $product = Product::withTrashed()->where('slug', self::SLUG)->first();
        if (! $product || $product->variants()->where('size', self::TALLA)->exists()) {
            return;
        }

        DB::transaction(function () use ($product) {
            $product->variants()->create([
                'size' => self::TALLA,
                'sku' => ProductVariant::skuFor((string) $product->sku, self::TALLA),
                'stock' => self::STOCK,
                'is_active' => true,
            ]);

            InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => null,
                'type' => 'in',
                'quantity' => self::STOCK,
                'reason' => 'Nueva talla '.self::TALLA.' con '.self::STOCK.' unidades (pedido de Gerson, 23-nov-2026)',
            ]);
        });
    }

    public function down(): void
    {
        // No se borra la variante automáticamente: se gestiona desde el panel.
    }
};
