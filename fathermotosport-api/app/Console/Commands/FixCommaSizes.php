<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Support\SizeCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCommaSizes extends Command
{
    protected $signature = 'products:fix-comma-sizes
                            {--apply : Aplica los cambios (sin este flag solo muestra qué haría)}
                            {--dry-run : Solo muestra qué haría (comportamiento por defecto)}';

    protected $description = 'Separa variantes con varias tallas escritas en un solo campo ("M, L, XL" o "M L XL") en una variante por talla.';

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Usá --apply o --dry-run, no ambos.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'Modo APPLY: se aplicarán los cambios.' : 'Modo DRY-RUN: no se modificará nada.');

        // "% %" cubre tanto "M, L, XL" como "M L XL"; splitSizes() decide cuáles son casos reales.
        $variants = ProductVariant::with('product:id,name,sku')
            ->where('size', 'like', '% %')
            ->orderBy('product_id')
            ->get()
            ->filter(fn (ProductVariant $v) => $this->splitSizes($v->size) !== null)
            ->values();

        if ($variants->isEmpty()) {
            $this->info('No hay variantes con varias tallas en un solo campo.');

            return self::SUCCESS;
        }

        $reservedSkus = [];
        $newSkus = [];
        $fixedProducts = [];
        $skipped = 0;

        foreach ($variants as $variant) {
            $sizes = collect($this->splitSizes($variant->size));

            $productName = $variant->product?->name ?? $variant->product_id;
            $this->newLine();
            $this->line("<comment>{$productName}</comment> — {$variant->sku} · talla \"{$variant->size}\" · stock {$variant->stock}");

            if ($sizes->count() < 2) {
                $this->warn('  Omitida: al separar queda una sola talla; revisar manualmente.');
                $skipped++;

                continue;
            }

            // Si alguna talla ya existe en el producto, crearla duplicaría la variante
            // (el color ya no distingue variantes): se omite la fila para revisión manual.
            $existing = ProductVariant::where('product_id', $variant->product_id)
                ->where('id', '!=', $variant->id)
                ->whereIn('size', $sizes)
                ->pluck('size');

            if ($existing->isNotEmpty()) {
                $this->warn('  Omitida: el producto ya tiene variantes con talla '.$existing->implode(', ').'; revisar manualmente.');
                $skipped++;

                continue;
            }

            $stocks = $this->splitStock((int) $variant->stock, $sizes->count());

            $rows = $sizes->map(function (string $size, int $i) use ($variant, $stocks, &$reservedSkus) {
                $sku = $this->uniqueSku($this->skuForSize($variant, $size), $reservedSkus);
                $reservedSkus[] = $sku;

                return [
                    'product_id' => $variant->product_id,
                    'size' => $size,
                    'finish' => $variant->finish,
                    'sku' => $sku,
                    'stock' => $stocks[$i],
                    'is_active' => $variant->is_active,
                ];
            });

            $this->table(['Nueva talla', 'SKU', 'Stock'], $rows->map(fn ($r) => [$r['size'], $r['sku'], $r['stock']]));
            $this->line('  Se elimina la variante original '.$variant->sku);

            if ($apply) {
                DB::transaction(function () use ($variant, $rows) {
                    foreach ($rows as $row) {
                        ProductVariant::create($row);
                    }
                    $variant->delete();
                });
            }

            $newSkus = array_merge($newSkus, $rows->pluck('sku')->all());
            $fixedProducts[$variant->product_id] = $productName;
        }

        $this->newLine();
        $this->info('─── Resumen '.($apply ? '(aplicado)' : '(dry-run, nada fue modificado)').' ───');
        $this->line('Variantes combinadas encontradas: '.$variants->count());
        $this->line('Variantes omitidas: '.$skipped);
        $this->line('Productos '.($apply ? 'corregidos' : 'a corregir').': '.count($fixedProducts));
        foreach ($fixedProducts as $name) {
            $this->line('  • '.$name);
        }
        $this->line('SKUs '.($apply ? 'creados' : 'a crear').': '.count($newSkus));
        foreach ($newSkus as $sku) {
            $this->line('  • '.$sku);
        }

        if (! $apply && $fixedProducts) {
            $this->newLine();
            $this->comment('Para aplicar los cambios: php artisan products:fix-comma-sizes --apply');
        }

        return self::SUCCESS;
    }

    /**
     * Devuelve las tallas contenidas en un valor combinado, o null si no es un caso a dividir.
     *  - Con ", " (ej. "M, L, XL"): se separa por comas.
     *  - Con espacios (ej. "M L XL"): solo si son 2+ palabras y TODAS son tallas válidas
     *    de SizeCatalog, para no partir textos legítimos como "Talla única".
     *
     * @return string[]|null
     */
    private function splitSizes(?string $size): ?array
    {
        $size = trim((string) $size);

        if (str_contains($size, ', ')) {
            $parts = preg_split('/\s*,\s*/', $size);
        } else {
            $parts = preg_split('/\s+/', $size);
            $valid = SizeCatalog::all();
            $parts = array_map('strtoupper', $parts);

            if (count($parts) < 2 || array_diff($parts, $valid) !== []) {
                return null;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $parts), 'strlen')));
    }

    /**
     * SKU de la variante nueva con el patrón del sistema: {SKU_PRODUCTO}-{TALLA}
     * (ej. "FMS-AGV-SYZGJT-M"). Si el producto no tuviera SKU, se usa el de la
     * variante original como base.
     */
    private function skuForSize(ProductVariant $variant, string $size): string
    {
        return ProductVariant::skuFor($variant->product?->sku ?: $variant->sku, $size);
    }

    /**
     * Reparte el stock en partes iguales; el resto se suma de a 1 empezando por
     * la primera talla (ej. 5 en 3 → 2, 2, 1).
     *
     * @return int[]
     */
    private function splitStock(int $stock, int $parts): array
    {
        $stock = max(0, $stock);
        $base = intdiv($stock, $parts);
        $rest = $stock % $parts;

        return array_map(fn ($i) => $base + ($i < $rest ? 1 : 0), range(0, $parts - 1));
    }

    /**
     * Devuelve un SKU que no exista en la base ni entre los ya reservados en esta
     * corrida, agregando -2, -3… si hace falta.
     */
    private function uniqueSku(string $base, array $reserved): string
    {
        $sku = $base;
        $n = 2;

        while (in_array($sku, $reserved, true) || ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$n++;
        }

        return $sku;
    }
}
