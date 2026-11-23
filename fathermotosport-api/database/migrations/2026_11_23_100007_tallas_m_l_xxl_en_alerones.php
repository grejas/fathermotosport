<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de Gerson (23-nov-2026): todos los alerones (slug «aleron-de-casco-…») pasan a
 * venderse por talla M, L y XXL, cada una con el stock que tenía el alerón sin talla.
 *
 * - La variante sin talla se convierte en la M (mismo id, para no romper carritos ni
 *   pedidos que la referencian) y se crean L y XXL con el mismo stock.
 * - Se deja un movimiento de inventario «in» por las unidades nuevas.
 * - Idempotente: un alerón que ya tiene alguna variante con talla no se toca.
 */
return new class extends Migration
{
    private const TALLAS = ['M', 'L', 'XXL'];

    public function up(): void
    {
        $alerones = Product::withTrashed()->where('slug', 'like', 'aleron-de-casco-%')->get();

        foreach ($alerones as $product) {
            $variantes = $product->variants()->get();
            if ($variantes->isEmpty() || $variantes->contains(fn ($v) => filled($v->size))) {
                continue;
            }

            DB::transaction(function () use ($product, $variantes) {
                $base = $variantes->first();
                $stock = (int) $base->stock;
                $sku = (string) $product->sku;

                // Si hubiera más de una variante sin talla, las extra quedan inactivas.
                $variantes->skip(1)->each(fn ($v) => $v->update(['is_active' => false]));

                $base->update([
                    'size' => self::TALLAS[0],
                    'sku' => ProductVariant::skuFor($sku, self::TALLAS[0]),
                    'is_active' => true,
                ]);

                foreach (array_slice(self::TALLAS, 1) as $talla) {
                    $product->variants()->create([
                        'size' => $talla,
                        'sku' => ProductVariant::skuFor($sku, $talla),
                        'stock' => $stock,
                        'is_active' => true,
                    ]);
                }

                if ($stock > 0) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'user_id' => null,
                        'type' => 'in',
                        'quantity' => $stock * (count(self::TALLAS) - 1),
                        'reason' => 'Tallas M, L y XXL con '.$stock.' unidades cada una (pedido de Gerson, 23-nov-2026)',
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // No se revierte automáticamente: las tallas se gestionan desde el panel.
    }
};
