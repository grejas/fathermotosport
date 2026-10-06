<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Venta cruzada post-compra: qué ofrecer después de un pedido pagado, y cómo armar el
 * pedido separado con lo que el cliente eligió.
 *
 * El precio y el descuento SIEMPRE se calculan acá a partir de la regla. Lo que manda el
 * navegador son solo referencias (qué regla, qué talla), nunca importes: es el mismo
 * criterio que OrderService, donde confiar en el precio del cliente permitiría armarse
 * un pedido a $1.
 */
class UpsellService
{
    /**
     * Cuántas ofertas entran en la pantalla de éxito. Es una decisión de diseño de esa
     * pantalla y no un parámetro de negocio, así que vive en el código.
     */
    public const MAX_OFERTAS = 3;

    /**
     * Cuánto dura la oferta desde que se mostró por primera vez. Es una regla interna:
     * al cliente no se le muestra ningún plazo, solo que la oferta es "para ahora".
     * Cubre recargar la página o volver de un pago fallido sin dejarla abierta para
     * siempre.
     */
    public const VENTANA_MINUTOS = 30;

    public const MENSAJE_OFERTA_CERRADA = 'Esta oferta ya no está disponible.';

    /**
     * Cuántas reglas puede tocar una sola creación masiva desde el panel. Es la única
     * fuente del tope: la usan el contador del modal, su aviso y la validación.
     */
    public const MAX_REGLAS_POR_LOTE = 1000;

    /** Filas por INSERT o UPDATE en la creación masiva: lejos del tope de parámetros de la base. */
    private const FILAS_POR_CONSULTA = 500;

    /**
     * Ofertas que corresponden a un pedido pagado, ya ordenadas y recortadas.
     *
     * @return Collection<int, UpsellRule>
     */
    public function ofertasPara(Order $original): Collection
    {
        if ($original->payment_status !== 'paid') {
            return collect();
        }

        // Nada de venta cruzada sobre una venta cruzada. Al pagar un upsell por PayPal el
        // cliente vuelve a /checkout/success con el pedido NUEVO, y sin esto se le
        // ofrecería otra tanda encadenada sin fin.
        if ($original->esUpsell()) {
            return collect();
        }

        // Si ya pagó una venta cruzada de este pedido, no se le ofrece de nuevo.
        if ($original->upsellOrders()->where('payment_status', 'paid')->exists()) {
            return collect();
        }

        // La oferta es solo para el momento del modal: rechazada o vencida, no vuelve.
        if ($this->ofertaCerrada($original)) {
            return collect();
        }

        $comprados = $this->productosDelPedido($original);

        if ($comprados->isEmpty()) {
            return collect();
        }

        return UpsellRule::active()
            ->with(['offerProduct.images', 'offerProduct.variants'])
            ->whereIn('trigger_product_id', $comprados)
            // No se ofrece algo que el cliente acaba de comprar.
            ->whereNotIn('offer_product_id', $comprados)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (UpsellRule $regla) => $this->variantesDisponibles($regla)->isNotEmpty())
            // Dos disparadores pueden ofrecer el mismo producto con descuentos distintos.
            // Gana el más alto: mostrar el peor existiendo uno mejor es indefendible si
            // el cliente lo compara. A igual descuento manda priority (ya está ordenado).
            ->sortByDesc('discount_percent')
            ->unique('offer_product_id')
            ->sortBy([['priority', 'desc'], ['id', 'asc']])
            ->take(self::MAX_OFERTAS)
            ->values();
    }

    /**
     * ¿El cliente ya rechazó la oferta, o pasó la ventana desde que se le mostró?
     * Sin haberse mostrado nunca, sigue abierta.
     */
    public function ofertaCerrada(Order $original): bool
    {
        if ($original->upsell_dismissed_at !== null) {
            return true;
        }

        return $original->upsell_offered_at !== null
            && $original->upsell_offered_at->copy()->addMinutes(self::VENTANA_MINUTOS)->isPast();
    }

    /**
     * Marca el comienzo de la ventana la primera vez que se muestran ofertas. Las
     * siguientes (recargar la página) no la corren: el plazo cuenta desde la primera.
     */
    public function registrarOfertaMostrada(Order $original): void
    {
        $marcadas = Order::whereKey($original->getKey())
            ->whereNull('upsell_offered_at')
            ->update(['upsell_offered_at' => now()]);

        if ($marcadas > 0) {
            $original->refresh();
        }
    }

    /**
     * El cliente cerró el modal sin comprar: la oferta deja de existir. Idempotente:
     * repetirlo no mueve la fecha del primer rechazo.
     *
     * El pedido de venta cruzada que hubiera quedado sin pagar se cancela, para que
     * no se pueda cobrar después por otro camino una oferta que ya no existe.
     */
    public function descartar(Order $original): void
    {
        DB::transaction(function () use ($original) {
            Order::whereKey($original->getKey())
                ->whereNull('upsell_dismissed_at')
                ->update(['upsell_dismissed_at' => now()]);

            $original->upsellOrders()
                ->where('payment_status', 'pending')
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        });

        $original->refresh();
    }

    /**
     * Variantes del producto ofrecido que se pueden comprar de verdad.
     *
     * @return Collection<int, ProductVariant>
     */
    public function variantesDisponibles(UpsellRule $regla): Collection
    {
        // Producto ofrecido inactivo o borrado: no hay nada que ofrecer.
        if (! $regla->offerProduct?->is_active) {
            return collect();
        }

        return $regla->offerProduct->variants
            ->filter(fn (ProductVariant $v) => $v->is_active && $v->stock > 0)
            ->values();
    }

    /**
     * Crea (o rehace) el pedido de venta cruzada con lo que el cliente eligió.
     *
     * @param  array<int, array{rule_id: int|string, variant_id: string}>  $seleccion
     *
     * @throws ValidationException
     */
    public function crearPedido(Order $original, array $seleccion): Order
    {
        if ($original->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'order' => ['Solo se puede agregar a un pedido ya pagado.'],
            ]);
        }

        // 409 y no 422: la selección puede estar perfecta, lo que ya no existe es la
        // oferta. El navegador lo distingue para cerrar el modal con un aviso amable.
        if ($this->ofertaCerrada($original)) {
            throw new ConflictHttpException(self::MENSAJE_OFERTA_CERRADA);
        }

        $comprados = $this->productosDelPedido($original);
        $lineas = $this->validarSeleccion($seleccion, $comprados);

        return DB::transaction(function () use ($original, $lineas) {
            // Reutiliza el pedido pendiente si el cliente ya había elegido antes y no
            // llegó a pagar: cada clic no debe dejar un pedido muerto. Se le reemplazan
            // los items, y el pago detecta solo que el monto cambió.
            $upsell = $original->upsellOrders()
                ->where('payment_status', 'pending')
                ->where('status', 'pending')
                ->latest()
                ->first();

            $subtotal = array_sum(array_column($lineas, 'subtotal'));
            $descuento = round(array_sum(array_column($lineas, 'descuento')), 2);

            $datos = [
                'subtotal' => $subtotal,
                'discount' => $descuento,
                // Viaja con el pedido original, así que no se cobra envío de nuevo.
                'shipping' => 0.00,
                'shipping_option_id' => null,
                'shipping_method_name' => null,
                'tax' => 0.00,
                'total' => round($subtotal - $descuento, 2),
            ];

            if ($upsell) {
                $upsell->items()->delete();
                $upsell->update($datos);
            } else {
                $upsell = Order::create($datos + [
                    'upsell_of_order_id' => $original->id,
                    'user_id' => $original->user_id,
                    // Se reutiliza la dirección del original: no hace falta pedir
                    // ningún dato de nuevo, ni duplicar la fila de addresses.
                    'address_id' => $original->address_id,
                    'guest_email' => $original->guest_email,
                    // Los avisos de la venta cruzada van al mismo email que los del
                    // pedido original.
                    'notification_email' => $original->notification_email,
                    'email_verificado_por' => $original->email_verificado_por,
                    'country' => $original->country,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'shipping_status' => 'pending',
                ]);
            }

            foreach ($lineas as $linea) {
                /** @var ProductVariant $variant */
                $variant = $linea['variant'];

                OrderItem::create([
                    'order_id' => $upsell->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    // Precio COMPLETO en el item y el ahorro en orders.discount, igual
                    // que los cupones: el reporte distingue lo vendido de lo regalado.
                    'unit_price' => $linea['precio'],
                    'subtotal' => $linea['subtotal'],
                    'size' => $variant->size,
                    'color' => $variant->product->color,
                ]);
            }

            return $upsell->fresh(['items.variant.product', 'address']);
        });
    }

    /**
     * Valida la selección contra la base y devuelve las líneas ya con precios calculados.
     *
     * @param  array<int, array{rule_id: int|string, variant_id: string}>  $seleccion
     * @param  Collection<int, string>  $comprados
     * @return array<int, array{variant: ProductVariant, precio: float, subtotal: float, descuento: float}>
     *
     * @throws ValidationException
     */
    private function validarSeleccion(array $seleccion, Collection $comprados): array
    {
        $lineas = [];
        $ofrecidos = [];

        foreach ($seleccion as $i => $item) {
            $regla = UpsellRule::active()
                ->with('offerProduct.variants')
                ->find($item['rule_id']);

            if (! $regla || ! $regla->offerProduct?->is_active) {
                throw ValidationException::withMessages([
                    "items.{$i}.rule_id" => ['Esa oferta ya no está disponible.'],
                ]);
            }

            if (! $comprados->contains($regla->trigger_product_id)) {
                throw ValidationException::withMessages([
                    "items.{$i}.rule_id" => ['Esa oferta no corresponde a este pedido.'],
                ]);
            }

            if ($comprados->contains($regla->offer_product_id)) {
                throw ValidationException::withMessages([
                    "items.{$i}.rule_id" => ['Ya compraste ese producto en este pedido.'],
                ]);
            }

            if (in_array($regla->offer_product_id, $ofrecidos, true)) {
                throw ValidationException::withMessages([
                    "items.{$i}.rule_id" => ['Ese producto está repetido en la selección.'],
                ]);
            }

            $variant = $this->variantesDisponibles($regla)
                ->firstWhere('id', $item['variant_id']);

            if (! $variant) {
                throw ValidationException::withMessages([
                    "items.{$i}.variant_id" => ['Esa talla no está disponible.'],
                ]);
            }

            $precio = $regla->precioBase();
            $conDescuento = $regla->precioConDescuento();

            $ofrecidos[] = $regla->offer_product_id;
            $lineas[] = [
                'variant' => $variant,
                'precio' => $precio,
                'subtotal' => $precio,
                'descuento' => round($precio - $conDescuento, 2),
            ];
        }

        return $lineas;
    }

    /**
     * Ids de los productos que contiene un pedido.
     *
     * @return Collection<int, string>
     */
    private function productosDelPedido(Order $order): Collection
    {
        return $order->items()
            ->with('variant:id,product_id')
            ->get()
            ->map(fn (OrderItem $item) => $item->variant?->product_id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Productos de un lado del lote (disparadores u ofertas): los activos de la
     * categoría (y sus subcategorías) más los elegidos a mano, sin repetir.
     *
     * @param  array<int, string>  $productIds
     * @return Collection<int, string>
     */
    public function productosDelLote(?int $categoryId, array $productIds): Collection
    {
        $deCategoria = $categoryId
            ? Product::where('is_active', true)
                ->whereIn('category_id', $this->categoriaConDescendientes($categoryId))
                ->pluck('id')
            : collect();

        return $deCategoria->merge($productIds)->filter()->unique()->values();
    }

    /**
     * Cada combinación disparador × oferta, salvo la de un producto consigo mismo.
     *
     * @param  Collection<int, string>  $triggerIds
     * @param  Collection<int, string>  $offerIds
     * @return Collection<int, array{trigger: string, offer: string}>
     */
    public function paresDelLote(Collection $triggerIds, Collection $offerIds): Collection
    {
        return $triggerIds
            ->crossJoin($offerIds)
            ->reject(fn (array $par) => $par[0] === $par[1])
            ->map(fn (array $par) => ['trigger' => $par[0], 'offer' => $par[1]])
            ->values();
    }

    /**
     * Pares del lote que ya tienen regla (el índice único no deja repetirlos).
     *
     * @param  Collection<int, array{trigger: string, offer: string}>  $pares
     * @return Collection<int, array{trigger: string, offer: string}>
     */
    public function paresExistentes(Collection $pares): Collection
    {
        $existentes = $this->reglasExistentes($pares)
            ->map(fn (UpsellRule $r) => $r->trigger_product_id.'|'.$r->offer_product_id)
            ->flip();

        return $pares->filter(fn (array $p) => $existentes->has($p['trigger'].'|'.$p['offer']))->values();
    }

    /**
     * Reglas ya cargadas para los pares del lote, en una sola consulta. Los whereIn
     * traen el producto cartesiano de disparadores y ofertas, así que se filtra a los
     * pares pedidos.
     *
     * @param  Collection<int, array{trigger: string, offer: string}>  $pares
     * @return Collection<int, UpsellRule>
     */
    private function reglasExistentes(Collection $pares, bool $bloquear = false): Collection
    {
        if ($pares->isEmpty()) {
            return collect();
        }

        $consulta = UpsellRule::whereIn('trigger_product_id', $pares->pluck('trigger')->unique())
            ->whereIn('offer_product_id', $pares->pluck('offer')->unique());

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $pedidos = $pares->map(fn (array $p) => $p['trigger'].'|'.$p['offer'])->flip();

        return $consulta->get(['id', 'trigger_product_id', 'offer_product_id'])
            ->filter(fn (UpsellRule $r) => $pedidos->has($r->trigger_product_id.'|'.$r->offer_product_id))
            ->values();
    }

    /**
     * Disparadores que, tras el lote, tendrían más reglas activas que lugares en la
     * pantalla de éxito: el cliente verá solo las MAX_OFERTAS de mayor prioridad.
     *
     * @param  Collection<int, array{trigger: string, offer: string}>  $pares
     * @return Collection<int, string> Nombres, ordenados.
     */
    public function disparadoresConExcesoDeOfertas(Collection $pares): Collection
    {
        if ($pares->isEmpty()) {
            return collect();
        }

        $triggerIds = $pares->pluck('trigger')->unique();

        $activas = UpsellRule::active()
            ->whereIn('trigger_product_id', $triggerIds)
            ->get(['trigger_product_id', 'offer_product_id'])
            ->groupBy('trigger_product_id')
            ->map(fn (Collection $reglas) => $reglas->pluck('offer_product_id'));

        $excedidos = $pares->groupBy('trigger')
            ->filter(fn (Collection $nuevos, string $trigger) => ($activas[$trigger] ?? collect())
                ->merge($nuevos->pluck('offer'))
                ->unique()
                ->count() > self::MAX_OFERTAS)
            ->keys();

        return Product::whereIn('id', $excedidos)->orderBy('name')->pluck('name');
    }

    /**
     * Crea en una sola transacción una regla por cada par disparador → oferta.
     *
     * Las que ya existen se omiten, o con $sobrescribir se les actualiza solo el
     * descuento. $descuentosPorOferta reemplaza el global para los productos ofrecidos
     * que el admin quiso tratar aparte. Si algo falla no queda nada creado a medias.
     *
     * @param  Collection<int, array{trigger: string, offer: string}>  $pares
     * @param  array<string, int>  $descuentosPorOferta  offer_product_id => porcentaje
     * @return array{creadas: Collection<int, string>, existentes: Collection<int, string>}
     *               Cada par como "Disparador → Ofrecido".
     */
    public function crearReglasEnLote(
        Collection $pares,
        int $descuento,
        int $prioridad,
        bool $sobrescribir = false,
        array $descuentosPorOferta = [],
    ): array {
        if ($pares->isEmpty()) {
            throw ValidationException::withMessages([
                'triggers' => 'Elegí disparadores y ofertas: con lo elegido no queda ninguna combinación válida.',
            ]);
        }

        if ($pares->count() > self::MAX_REGLAS_POR_LOTE) {
            throw ValidationException::withMessages([
                'triggers' => sprintf(
                    'Son %d reglas y el tope es %d por vez. Achicá la selección y repetí en otra tanda.',
                    $pares->count(),
                    self::MAX_REGLAS_POR_LOTE
                ),
            ]);
        }

        $ofertas = $pares->pluck('offer')->unique();

        foreach ([$descuento, ...array_values($descuentosPorOferta)] as $porcentaje) {
            if ($porcentaje < 1 || $porcentaje > 90) {
                throw ValidationException::withMessages(['discount_percent' => 'El descuento va de 1% a 90%.']);
            }
        }

        if (array_diff(array_keys($descuentosPorOferta), $ofertas->all())) {
            throw ValidationException::withMessages([
                'descuentos' => 'Hay descuentos por producto para productos que no están entre las ofertas.',
            ]);
        }

        $descuentoDe = fn (string $offerId): int => $descuentosPorOferta[$offerId] ?? $descuento;

        // Todo por lotes: con 1000 reglas, una consulta por regla haría el modal lento y
        // la transacción larga. La cantidad de consultas no crece con el lote.
        return DB::transaction(function () use ($pares, $prioridad, $sobrescribir, $descuentoDe) {
            $filasExistentes = $this->reglasExistentes($pares, bloquear: true);
            $existentes = $filasExistentes
                ->map(fn (UpsellRule $r) => ['trigger' => $r->trigger_product_id, 'offer' => $r->offer_product_id])
                ->values();
            $claves = $existentes->map(fn (array $p) => $p['trigger'].'|'.$p['offer'])->flip();
            $nuevos = $pares->reject(fn (array $p) => $claves->has($p['trigger'].'|'.$p['offer']))->values();

            // insert() directo y no create(): el modelo no tiene eventos ni observers,
            // así que solo faltan las fechas.
            $ahora = now();
            $filas = $nuevos->map(fn (array $par) => [
                'trigger_product_id' => $par['trigger'],
                'offer_product_id' => $par['offer'],
                'discount_percent' => $descuentoDe($par['offer']),
                'priority' => $prioridad,
                'is_active' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            foreach ($filas->chunk(self::FILAS_POR_CONSULTA) as $bloque) {
                UpsellRule::insert($bloque->values()->all());
            }

            if ($sobrescribir) {
                // Una actualización por cada descuento distinto (el global y los propios
                // por producto), no una por regla.
                $porDescuento = $filasExistentes->groupBy(fn (UpsellRule $r) => $descuentoDe($r->offer_product_id));

                foreach ($porDescuento as $porcentaje => $reglas) {
                    foreach ($reglas->pluck('id')->chunk(self::FILAS_POR_CONSULTA) as $ids) {
                        UpsellRule::whereIn('id', $ids->all())->update(['discount_percent' => $porcentaje]);
                    }
                }
            }

            $nombres = Product::whereIn('id', $pares->pluck('trigger')->merge($pares->pluck('offer'))->unique())
                ->pluck('name', 'id');
            $etiqueta = fn (array $p) => $nombres[$p['trigger']].' → '.$nombres[$p['offer']];

            return [
                'creadas' => $nuevos->map($etiqueta)->sort()->values(),
                'existentes' => $existentes->map($etiqueta)->sort()->values(),
            ];
        });
    }

    /** @return array<int, int> */
    private function categoriaConDescendientes(int $categoryId): array
    {
        $ids = [$categoryId];
        $nivel = [$categoryId];

        // Pocos niveles en la práctica (Cascos → Integrales); el límite evita un bucle
        // infinito si alguien armara un ciclo de padres en el panel.
        for ($i = 0; $i < 10 && $nivel; $i++) {
            $nivel = Category::whereIn('parent_id', $nivel)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $nivel);
        }

        return $ids;
    }
}
