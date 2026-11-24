<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Upsells para quien compra un casco (pedido de Gerson, 24-nov-2026), en este orden:
 *
 * 1. Visor «Visor Pista AGV GP R/GP RR/Corsa R» al 25 %.
 * 2. Lente «Pinlock 120 Max Vision AGV Pista GP R/GP RR/Corsa-R» al 25 %.
 *    Los dos solo calzan en AGV Pista GP R, Pista GP RR y Corsa R: van producto →
 *    producto, solo para esos cascos.
 * 3. Guantes al 15 % (categoría Cascos → categoría Guantes: uno con stock).
 * 4. Riñoneras al 10 % (las 6, grupo «riñoneras»: se muestra una por pedido, rotando).
 * 5. Un segundo casco al 10 % (categoría Cascos → Cascos: uno con stock que no sea el
 *    que acaba de comprar).
 *
 * Idempotente: si la regla existe se actualizan descuento, prioridad, grupo y estado; si
 * un producto o categoría no existe, se anota en el log y se sigue. Se editan después
 * desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cascos = Category::where('slug', 'cascos')->first();

        if (! $cascos) {
            Log::warning('Reglas de upsell: no existe la categoría «cascos»; no se cargó ninguna.');

            return;
        }

        $compatibles = Product::where('category_id', $cascos->id)
            ->where(fn ($q) => $q->where('name', 'like', '%Pista GP R%')->orWhere('name', 'like', '%Corsa R%'))
            ->pluck('id');

        foreach ([
            ['Visor Pista AGV GP R%', 25, 50],
            ['Lente Pinlock%Pista GP R%', 25, 40],
        ] as [$patron, $descuento, $prioridad]) {
            $producto = Product::where('name', 'like', $patron)->orderBy('created_at')->first();

            if (! $producto) {
                Log::warning("Reglas de upsell: no se encontró el producto «{$patron}».");

                continue;
            }

            foreach ($compatibles as $casco) {
                $this->guardar(['trigger_product_id' => $casco, 'offer_product_id' => $producto->id], $descuento, $prioridad);
            }
        }

        if ($guantes = Category::where('slug', 'guantes')->first()) {
            $this->guardar(['trigger_category_id' => $cascos->id, 'offer_category_id' => $guantes->id], 15, 30);
        } else {
            Log::warning('Reglas de upsell: no existe la categoría «guantes».');
        }

        // trim por si el nombre empieza con espacio (hay una cargada así).
        $rinoneras = Product::whereRaw("TRIM(name) LIKE 'Riñonera%'")->pluck('id');

        foreach ($rinoneras as $rinonera) {
            $this->guardar(['trigger_category_id' => $cascos->id, 'offer_product_id' => $rinonera], 10, 20, 'riñoneras');
        }

        $this->guardar(['trigger_category_id' => $cascos->id, 'offer_category_id' => $cascos->id], 10, 10);
    }

    /** @param  array<string, mixed>  $clave */
    private function guardar(array $clave, int $descuento, int $prioridad, ?string $grupo = null): void
    {
        $completa = $clave + [
            'trigger_product_id' => null,
            'trigger_category_id' => null,
            'offer_product_id' => null,
            'offer_category_id' => null,
        ];

        UpsellRule::updateOrCreate($completa, [
            'discount_percent' => $descuento,
            'priority' => $prioridad,
            'upsell_group' => $grupo,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // No se revierte: las reglas se gestionan desde el panel.
    }
};
