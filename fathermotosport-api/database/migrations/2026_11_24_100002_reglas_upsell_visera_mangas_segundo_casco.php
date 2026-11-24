<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Pedido de Gerson (24-nov-2026), ofertas para quien compra un casco:
 *
 * 1. Visera extra «Visor Pista AGV GP R/GP RR/Corsa R» al 50 %. Solo para los cascos a
 *    los que les calza (AGV Pista GP R, Pista GP RR y Corsa R), por eso la regla va
 *    producto → producto y no por categoría.
 * 2. «Manga Dainese Negro» al 30 %, para cualquier casco (categoría Cascos).
 * 3. Un segundo casco al 20 %, para cualquier casco (categoría Cascos → Cascos). El
 *    servicio elige uno con stock que no sea el que acaba de comprar.
 *
 * Prioridad en ese orden (la visera primero). Idempotente: si la regla ya existe se
 * actualizan descuento, prioridad y estado; si un producto o la categoría no existen,
 * se anota en el log y se sigue. Se pueden editar después desde el panel.
 */
return new class extends Migration
{
    private const VISERA = 50;

    private const MANGAS = 30;

    private const SEGUNDO_CASCO = 20;

    public function up(): void
    {
        $cascos = Category::where('slug', 'cascos')->first();

        if (! $cascos) {
            Log::warning('Reglas de upsell: no existe la categoría «cascos»; no se cargó ninguna.');

            return;
        }

        $visera = Product::where('name', 'like', 'Visor Pista AGV GP R%')->orderBy('created_at')->first();

        if ($visera) {
            $compatibles = Product::where('category_id', $cascos->id)
                ->where(fn ($q) => $q->where('name', 'like', '%Pista GP R%')->orWhere('name', 'like', '%Corsa R%'))
                ->pluck('id');

            foreach ($compatibles as $casco) {
                $this->guardar(['trigger_product_id' => $casco, 'offer_product_id' => $visera->id], self::VISERA, 30);
            }
        } else {
            Log::warning('Reglas de upsell: no se encontró la visera «Visor Pista AGV GP R…».');
        }

        $mangas = Product::where('name', 'like', 'Manga Dainese Negro%')->orderBy('created_at')->first();

        if ($mangas) {
            $this->guardar(['trigger_category_id' => $cascos->id, 'offer_product_id' => $mangas->id], self::MANGAS, 20);
        } else {
            Log::warning('Reglas de upsell: no se encontró «Manga Dainese Negro».');
        }

        $this->guardar(['trigger_category_id' => $cascos->id, 'offer_category_id' => $cascos->id], self::SEGUNDO_CASCO, 10);
    }

    /** @param  array<string, mixed>  $clave */
    private function guardar(array $clave, int $descuento, int $prioridad): void
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
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // No se revierte: las reglas se gestionan desde el panel.
    }
};
