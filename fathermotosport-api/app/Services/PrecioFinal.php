<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * El precio que paga el cliente por un producto. Es la ÚNICA regla de precio: la usan
 * la API de productos, el endpoint de precios del carrito, el pedido (lo que se cobra),
 * el upsell y el panel, para que lo mostrado y lo cobrado no se separen nunca.
 *
 * Candidatos, sin sumarse:
 *  - el precio propio del producto: sale_price si tiene, si no price (como siempre);
 *  - el descuento de su categoría o de cualquier categoría padre que esté vigente y del
 *    que el producto no esté excluido. Entre varias gana el porcentaje mayor, y se
 *    aplica sobre price (el de lista).
 * Gana el que deja el precio más bajo; a igual precio, el propio.
 *
 * Se calcula en vivo: no escribe precios. Las categorías se leen una sola vez por
 * petición (quedan en los atributos del request), así un listado no hace una consulta
 * por producto.
 */
class PrecioFinal
{
    public const ORIGEN_PRODUCTO = 'product';

    public const ORIGEN_CATEGORIA = 'category';

    private const CLAVE = 'precio_final.categorias';

    /**
     * @return array{final_price: string, discount_percent: int|float, discount_source: ?string}
     */
    public function de(Product $product, ?CarbonInterface $ahora = null): array
    {
        $lista = self::centavos($product->price);
        $propio = $product->sale_price !== null ? self::centavos($product->sale_price) : $lista;

        $final = $propio;
        $origen = $propio < $lista ? self::ORIGEN_PRODUCTO : null;
        $porcentaje = $origen ? $this->porcentajeEntre($lista, $propio) : 0;

        $deCategoria = $this->porcentajeDeCategoria($product, $ahora ?? now());
        if ($deCategoria > 0) {
            $conCategoria = self::aplicar($lista, $deCategoria);
            if ($conCategoria < $final) {
                $final = $conCategoria;
                $origen = self::ORIGEN_CATEGORIA;
                $porcentaje = self::numero($deCategoria);
            }
        }

        return [
            'final_price' => number_format($final / 100, 2, '.', ''),
            'discount_percent' => $porcentaje,
            'discount_source' => $origen,
        ];
    }

    /** Precio final como número, para sumar en el pedido y el upsell. */
    public function precio(Product $product): float
    {
        return (float) $this->de($product)['final_price'];
    }

    /**
     * El mayor porcentaje vigente entre la categoría del producto y sus padres, salvo las
     * categorías que lo excluyen. 0 si no hay ninguno.
     */
    public function porcentajeDeCategoria(Product $product, CarbonInterface $ahora): float
    {
        if (! $product->category_id) {
            return 0;
        }

        ['categorias' => $categorias, 'excluidos' => $excluidos] = $this->datos();

        $mayor = 0.0;
        $id = (int) $product->category_id;

        // Sube por los padres; el límite evita un bucle si hubiera un ciclo de padres.
        for ($i = 0; $i < 10 && $id && isset($categorias[$id]); $i++) {
            $categoria = $categorias[$id];

            if ($categoria->descuentoVigente($ahora) && ! isset($excluidos[$id][$product->id])) {
                $mayor = max($mayor, (float) $categoria->discount_percent);
            }

            $id = (int) $categoria->parent_id;
        }

        return $mayor;
    }

    /** Descarta lo leído en esta petición (al guardar una categoría o sus exclusiones). */
    public static function olvidar(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove(self::CLAVE);
        }
    }

    /**
     * @return array{categorias: array<int, Category>, excluidos: array<int, array<string, true>>}
     */
    private function datos(): array
    {
        $atributos = request()->attributes;

        if (! $atributos->has(self::CLAVE)) {
            $categorias = Category::query()
                ->get(['id', 'parent_id', 'discount_percent', 'discount_active', 'discount_starts_at', 'discount_ends_at'])
                ->keyBy('id')
                ->all();

            $conDescuento = array_keys(array_filter($categorias, fn (Category $c) => $c->discount_active));

            $excluidos = [];
            if ($conDescuento !== []) {
                DB::table('category_discount_exclusions')
                    ->whereIn('category_id', $conDescuento)
                    ->get(['category_id', 'product_id'])
                    ->each(function ($fila) use (&$excluidos) {
                        $excluidos[(int) $fila->category_id][(string) $fila->product_id] = true;
                    });
            }

            $atributos->set(self::CLAVE, ['categorias' => $categorias, 'excluidos' => $excluidos]);
        }

        return $atributos->get(self::CLAVE);
    }

    /** "430.00" → 43000. Se trabaja en centavos para no arrastrar errores de float. */
    private static function centavos(mixed $monto): int
    {
        return (int) round((float) $monto * 100);
    }

    /** Precio en centavos con el porcentaje aplicado, redondeado al centavo (mitad hacia arriba). */
    private static function aplicar(int $centavos, float $porcentaje): int
    {
        $centesimas = (int) round($porcentaje * 100);

        return intdiv($centavos * (10000 - $centesimas) + 5000, 10000);
    }

    /** El "-X %" del precio propio, entero como hasta ahora. */
    private function porcentajeEntre(int $lista, int $final): int
    {
        return $lista > 0 ? (int) round(($lista - $final) / $lista * 100) : 0;
    }

    /** 15.0 → 15, 7.5 → 7.5 (así el JSON muestra "15" y no "15.0"). */
    private static function numero(float $valor): int|float
    {
        return $valor == (int) $valor ? (int) $valor : round($valor, 2);
    }
}
