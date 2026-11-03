<?php

namespace App\Services;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Productos destacados del aviso de descuento de una categoría (remarketing, fase 3):
 * activos, con stock en alguna variante activa, de esa categoría (la misma que abre el
 * botón del correo), no excluidos del descuento y que hoy cuestan menos que su precio de
 * lista. Primero los que tienen foto; entre ellos, los más vendidos (pedidos pagados) y
 * después los más recientes.
 *
 * Se eligen al armar cada correo: el stock y los precios son los del momento del envío.
 * Los precios salen de PrecioFinal, la misma regla que cobra el pedido.
 */
class DestacadosDelAviso
{
    /** Candidatos que se leen antes de quedarse con los que de verdad tienen descuento. */
    private const CANDIDATOS = 12;

    public function __construct(private PrecioFinal $precios) {}

    /**
     * @return Collection<int, array{producto: Product, precio: string, precio_final: string}>
     */
    public function de(Category $category, int $maximo = 4): Collection
    {
        $vendidos = OrderItem::query()
            ->selectRaw('coalesce(sum(order_items.quantity), 0)')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereColumn('product_variants.product_id', 'products.id');

        return Product::query()
            ->active()
            ->where('category_id', $category->id)
            ->whereNotIn('products.id', $category->productosExcluidos()->select('products.id'))
            ->whereHas('variants', fn (Builder $q) => $q->where('is_active', true)->where('stock', '>', 0))
            ->with('images')
            ->select('products.*')
            ->selectSub($vendidos, 'vendidos')
            ->orderByDesc('vendidos')
            ->orderByDesc('created_at')
            ->limit(self::CANDIDATOS)
            ->get()
            ->map(fn (Product $p) => [
                'producto' => $p,
                'precio' => number_format((float) $p->price, 2, '.', ''),
                'precio_final' => $this->precios->de($p)['final_price'],
            ])
            ->filter(fn (array $d) => (float) $d['precio_final'] < (float) $d['precio'])
            // Los que tienen foto primero (orden estable: dentro, sigue ventas y fecha).
            ->sortBy(fn (array $d) => $d['producto']->primary_image ? 0 : 1)
            ->take($maximo)
            ->values();
    }
}
