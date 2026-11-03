<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cambios de precio y de oferta en uno o varios productos. Concentra las reglas para
 * que el panel (fila y acciones en lote) y cualquier script futuro las cumplan igual:
 * el precio siempre es mayor que cero y la oferta, si existe, es menor que el precio.
 */
class ProductPricingService
{
    /** Descuento máximo aceptado en lote: más allá casi seguro es un error de tipeo. */
    public const MAX_DISCOUNT_PERCENT = 90;

    /**
     * Fija precio y precio de oferta de un producto. $salePrice null o 0 = sin oferta.
     */
    public function setPrices(Product $product, float $price, ?float $salePrice = null): Product
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }

        $salePrice = $salePrice !== null && $salePrice > 0 ? round($salePrice, 2) : null;

        if ($salePrice !== null && $salePrice >= $price) {
            throw new InvalidArgumentException('El precio de oferta debe ser menor que el precio.');
        }

        $product->update(['price' => round($price, 2), 'sale_price' => $salePrice]);

        return $product;
    }

    /**
     * Sube o baja el precio normal un porcentaje (negativo = baja). Si la oferta
     * existente deja de ser menor que el nuevo precio, se quita. Devuelve cuántos
     * productos cambiaron.
     *
     * @param  iterable<Product>  $products
     */
    public function adjustPrice(iterable $products, float $percent): int
    {
        if ($percent <= -100) {
            throw new InvalidArgumentException('La baja no puede ser del 100 % o más.');
        }

        return DB::transaction(function () use ($products, $percent): int {
            $changed = 0;

            foreach ($products as $product) {
                $newPrice = round((float) $product->price * (1 + $percent / 100), 2);

                if ($newPrice < 0.01) {
                    continue;
                }

                $sale = $product->sale_price !== null ? (float) $product->sale_price : null;

                $product->update([
                    'price' => $newPrice,
                    'sale_price' => $sale !== null && $sale < $newPrice ? $sale : null,
                ]);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Pone en oferta: precio de oferta = precio × (1 − descuento %). Devuelve cuántos
     * productos cambiaron.
     *
     * @param  iterable<Product>  $products
     */
    public function applyDiscount(iterable $products, float $percent): int
    {
        if ($percent <= 0 || $percent > self::MAX_DISCOUNT_PERCENT) {
            throw new InvalidArgumentException(
                'El descuento debe estar entre 1 % y '.self::MAX_DISCOUNT_PERCENT.' %.'
            );
        }

        return DB::transaction(function () use ($products, $percent): int {
            $changed = 0;

            foreach ($products as $product) {
                $sale = round((float) $product->price * (1 - $percent / 100), 2);

                if ($sale < 0.01 || $sale >= (float) $product->price) {
                    continue;
                }

                $product->update(['sale_price' => $sale]);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Quita la oferta. Devuelve cuántos productos tenían una.
     *
     * @param  iterable<Product>  $products
     */
    public function clearSale(iterable $products): int
    {
        return DB::transaction(function () use ($products): int {
            $changed = 0;

            foreach ($products as $product) {
                if ($product->sale_price === null) {
                    continue;
                }

                $product->update(['sale_price' => null]);
                $changed++;
            }

            return $changed;
        });
    }
}
