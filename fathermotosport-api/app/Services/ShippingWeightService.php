<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class ShippingWeightService
{
    /** Peso asumido cuando el producto no tiene weight cargado. */
    public const DEFAULT_WEIGHT_KG = 1.0;

    /**
     * Peso total (kg) de una lista de items del carrito, con el peso real del producto.
     * Los productos sin peso cargado cuentan como DEFAULT_WEIGHT_KG y se registran en
     * el log para que el admin les cargue el peso real.
     *
     * @param  array<int, array{variant_id: string, quantity: int}>  $items
     */
    public function forItems(array $items): float
    {
        if ($items === []) {
            return 0.0;
        }

        $variants = ProductVariant::with('product:id,name,sku,weight')
            ->whereIn('id', array_column($items, 'variant_id'))
            ->get()
            ->keyBy('id');

        $total = 0.0;
        $sinPeso = [];

        foreach ($items as $item) {
            $variant = $variants->get($item['variant_id']);
            $quantity = max(1, (int) $item['quantity']);
            $product = $variant?->product;
            $weight = (float) ($product->weight ?? 0);

            if ($weight <= 0) {
                $weight = self::DEFAULT_WEIGHT_KG;
                if ($product) {
                    $sinPeso[$product->sku] = $product->name;
                }
            }

            $total += $weight * $quantity;
        }

        if ($sinPeso !== []) {
            Log::warning('Productos sin peso cargado: se asumió '.self::DEFAULT_WEIGHT_KG.' kg por unidad para calcular el envío.', [
                'productos' => collect($sinPeso)->map(fn ($name, $sku) => "{$sku} — {$name}")->values()->all(),
            ]);
        }

        return round($total, 3);
    }

    /**
     * Parsea el parámetro items de la query: "uuid:cantidad,uuid:cantidad".
     *
     * @return array<int, array{variant_id: string, quantity: int}>
     */
    public function parseItemsParam(?string $items): array
    {
        if (blank($items)) {
            return [];
        }

        return collect(explode(',', $items))
            ->map(function (string $pair) {
                [$variantId, $quantity] = array_pad(explode(':', trim($pair), 2), 2, '1');

                return ['variant_id' => trim($variantId), 'quantity' => (int) $quantity];
            })
            ->filter(fn (array $item) => $item['variant_id'] !== '')
            ->values()
            ->all();
    }
}
