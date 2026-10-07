<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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
    public const MAX_OFERTAS = 5;

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

        $categorias = $this->categoriasDelPedido($comprados);

        return UpsellRule::active()
            ->with(['offerProduct.images', 'offerProduct.variants'])
            ->where(fn ($q) => $q->whereIn('trigger_product_id', $comprados)
                ->orWhereIn('trigger_category_id', $categorias))
            // No se ofrece algo que el cliente acaba de comprar. Sin el whereNull, el
            // NOT IN sobre NULL descartaría las reglas que ofrecen una categoría.
            ->where(fn ($q) => $q->whereNull('offer_product_id')
                ->orWhereNotIn('offer_product_id', $comprados))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            // Una regla de categoría se resuelve a UN producto: el front identifica cada
            // oferta por su rule_id, y así los lugares se reparten entre reglas.
            ->map(fn (UpsellRule $regla) => $regla->ofreceCategoria()
                ? $this->productoDeCategoria($regla, $comprados)
                : $regla)
            ->filter()
            ->filter(fn (UpsellRule $regla) => $this->variantesDisponibles($regla)->isNotEmpty())
            // Dos disparadores pueden ofrecer el mismo producto con descuentos distintos.
            // Gana el más alto: mostrar el peor existiendo uno mejor es indefendible si
            // el cliente lo compara. A igual descuento manda priority (ya está ordenado).
            ->sortByDesc('discount_percent')
            ->unique('offer_product_id')
            // Orden en pantalla: prioridad; a igual prioridad, el mejor descuento primero.
            ->sortBy([['priority', 'desc'], ['discount_percent', 'desc'], ['id', 'asc']])
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
        $lineas = $this->validarSeleccion($seleccion, $comprados, $this->categoriasDelPedido($comprados));

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
     * @param  array<int, int>  $categorias  Las de los productos comprados, con sus ancestros.
     * @return array<int, array{variant: ProductVariant, precio: float, subtotal: float, descuento: float}>
     *
     * @throws ValidationException
     */
    private function validarSeleccion(array $seleccion, Collection $comprados, array $categorias): array
    {
        $lineas = [];
        $ofrecidos = [];

        foreach ($seleccion as $i => $item) {
            $regla = UpsellRule::active()
                ->with('offerProduct.variants')
                ->find($item['rule_id']);

            // En una regla de categoría el producto sale de la talla elegida, y tiene
            // que pertenecer a la categoría ofrecida (o a una subcategoría).
            if ($regla?->ofreceCategoria()) {
                $producto = ProductVariant::with('product.variants')->find($item['variant_id'])?->product;

                if (! $producto || ! in_array($producto->category_id, $this->categoriaConDescendientes((int) $regla->offer_category_id))) {
                    throw ValidationException::withMessages([
                        "items.{$i}.variant_id" => ['Ese producto no corresponde a la oferta.'],
                    ]);
                }

                $regla = $regla->paraProducto($producto);
            }

            if (! $regla || ! $regla->offerProduct?->is_active) {
                throw ValidationException::withMessages([
                    "items.{$i}.rule_id" => ['Esa oferta ya no está disponible.'],
                ]);
            }

            $disparada = ($regla->trigger_product_id !== null && $comprados->contains($regla->trigger_product_id))
                || ($regla->trigger_category_id !== null && in_array($regla->trigger_category_id, $categorias));

            if (! $disparada) {
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
     * Categorías que disparan reglas para un pedido: las de sus productos y todas sus
     * ancestras, porque una regla sobre "Cascos" vale también para "Cascos → Integrales".
     *
     * @param  Collection<int, string>  $comprados
     * @return array<int, int>
     */
    private function categoriasDelPedido(Collection $comprados): array
    {
        $ids = Product::whereIn('id', $comprados)->whereNotNull('category_id')
            ->distinct()->pluck('category_id')->map(fn ($id) => (int) $id)->all();
        $nivel = $ids;

        // Mismo límite que categoriaConDescendientes, por si hubiera un ciclo de padres.
        for ($i = 0; $i < 10 && $nivel; $i++) {
            $nivel = Category::whereIn('id', $nivel)->whereNotNull('parent_id')
                ->whereNotIn('parent_id', $ids)->pluck('parent_id')->map(fn ($id) => (int) $id)->unique()->all();
            $ids = array_merge($ids, $nivel);
        }

        return $ids;
    }

    /**
     * La regla de categoría aplicada al producto que se muestra: uno activo, con alguna
     * talla en stock y que el cliente no acaba de comprar. Primero los destacados y
     * populares; null si la categoría no tiene nada que ofrecer.
     *
     * @param  Collection<int, string>  $comprados
     */
    private function productoDeCategoria(UpsellRule $regla, Collection $comprados): ?UpsellRule
    {
        $producto = Product::query()
            ->with(['images', 'variants'])
            ->where('is_active', true)
            ->whereIn('category_id', $this->categoriaConDescendientes((int) $regla->offer_category_id))
            ->whereNotIn('id', $comprados)
            ->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('stock', '>', 0))
            ->orderByDesc('is_featured')
            ->orderByDesc('is_popular')
            ->latest()
            ->first();

        return $producto ? $regla->paraProducto($producto) : null;
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

    /**
     * Qué haría el formulario de regla individual con estos datos: las ofertas que
     * quedan por crear y las que ya tienen regla con ese disparador (se omiten). Lo usan
     * el aviso en vivo del formulario y el guardado, así los dos dicen lo mismo.
     *
     * @param  array<string, mixed>  $datos  Ver guardarReglas().
     * @return array{nuevas: Collection<int, string|int>, existentes: Collection<int, string|int>}
     *               Ids de producto ofrecido, o el id de la categoría ofrecida.
     */
    public function planDeReglas(array $datos, ?int $exceptoId = null): array
    {
        $ofertas = $this->ofertasDelFormulario($datos);

        if ($ofertas->isEmpty() || ! $this->tieneDisparador($datos)) {
            return ['nuevas' => $ofertas, 'existentes' => collect()];
        }

        $columna = filled($datos['offer_category_id'] ?? null) ? 'offer_category_id' : 'offer_product_id';

        $existentes = $this->conDisparador(UpsellRule::query(), $datos)
            ->whereIn($columna, $ofertas)
            ->when($exceptoId, fn ($q) => $q->whereKeyNot($exceptoId))
            ->pluck($columna);

        // Comparación laxa: el id de categoría puede llegar como texto del formulario.
        return [
            'nuevas' => $ofertas->reject(fn ($id) => $existentes->contains($id))->values(),
            'existentes' => $ofertas->filter(fn ($id) => $existentes->contains($id))->values(),
        ];
    }

    /**
     * Cuántas ofertas activas distintas tendría el disparador tras guardar. Si pasa de
     * MAX_OFERTAS, el cliente verá solo las de mayor prioridad.
     *
     * @param  array<string, mixed>  $datos  Ver guardarReglas().
     */
    public function ofertasActivasTrasGuardar(array $datos, ?int $exceptoId = null): int
    {
        if (! $this->tieneDisparador($datos)) {
            return 0;
        }

        $clave = fn (UpsellRule $r) => $r->offer_category_id !== null ? 'c'.$r->offer_category_id : 'p'.$r->offer_product_id;

        $actuales = $this->conDisparador(UpsellRule::active(), $datos)
            ->when($exceptoId, fn ($q) => $q->whereKeyNot($exceptoId))
            ->get(['offer_product_id', 'offer_category_id'])
            // toBase(): vacía, la colección Eloquent no sabría fusionar textos.
            ->toBase()
            ->map($clave);

        $nuevas = ($datos['is_active'] ?? true)
            ? $this->ofertasDelFormulario($datos)->map(fn ($id) => (filled($datos['offer_category_id'] ?? null) ? 'c' : 'p').$id)
            : collect();

        return $actuales->merge($nuevas)->unique()->count();
    }

    /**
     * Guarda el formulario de regla individual: un disparador (producto o categoría) y
     * una categoría o varios productos ofrecidos. Una regla por producto; las que ya
     * existen con ese disparador se omiten y se informan. Al editar, la regla editada
     * conserva su producto si sigue elegido (si no, toma el primero) y el resto se crea.
     *
     * @param  array{trigger_product_id?: ?string, trigger_category_id?: int|string|null, offer_category_id?: int|string|null, offer_product_ids?: array<int, string>, discount_percent: int|string, descuentos?: array<string, int|string>, priority?: int|string|null, is_active?: bool}  $datos
     * @return array{regla: UpsellRule, creadas: Collection<int, string>, omitidas: Collection<int, string>}
     *               Las listas, con el nombre de lo ofrecido.
     *
     * @throws ValidationException  Claves: trigger, offer_product_ids, offer_category_id, discount_percent, descuentos.
     */
    public function guardarReglas(array $datos, ?UpsellRule $regla = null): array
    {
        if (! $this->tieneDisparador($datos)) {
            throw ValidationException::withMessages(['trigger' => 'Elegí el disparador: un producto o una categoría.']);
        }

        $porCategoria = filled($datos['offer_category_id'] ?? null);
        $campoOferta = $porCategoria ? 'offer_category_id' : 'offer_product_ids';
        $ofertas = $this->ofertasDelFormulario($datos);

        if ($ofertas->isEmpty()) {
            throw ValidationException::withMessages([$campoOferta => 'Elegí qué ofrecer.']);
        }

        if (! $porCategoria && filled($datos['trigger_product_id'] ?? null) && $ofertas->contains($datos['trigger_product_id'])) {
            throw ValidationException::withMessages([
                'offer_product_ids' => 'El disparador no puede ofrecerse a sí mismo.',
            ]);
        }

        $descuento = (int) $datos['discount_percent'];
        $descuentos = $porCategoria ? [] : array_map('intval', $datos['descuentos'] ?? []);

        foreach ([$descuento, ...array_values($descuentos)] as $porcentaje) {
            if ($porcentaje < 1 || $porcentaje > 90) {
                throw ValidationException::withMessages(['discount_percent' => 'El descuento va de 1% a 90%.']);
            }
        }

        if (array_diff(array_keys($descuentos), $ofertas->all())) {
            throw ValidationException::withMessages([
                'descuentos' => 'Hay descuentos propios para productos que no están entre los ofrecidos.',
            ]);
        }

        ['nuevas' => $nuevas, 'existentes' => $existentes] = $this->planDeReglas($datos, $regla?->getKey());
        $nombreDe = $this->nombresDeOfertas($ofertas, $porCategoria);

        if ($nuevas->isEmpty()) {
            throw ValidationException::withMessages([
                $campoOferta => $existentes->count() === 1
                    ? sprintf('Ya existe una regla con este disparador para %s. Buscala en el listado para editarla.', $nombreDe($existentes->first()))
                    : 'Todo lo elegido ya tiene una regla con este disparador: '.$existentes->map($nombreDe)->implode(', ').'.',
            ]);
        }

        $base = [
            'trigger_product_id' => filled($datos['trigger_category_id'] ?? null) ? null : $datos['trigger_product_id'],
            'trigger_category_id' => filled($datos['trigger_category_id'] ?? null) ? (int) $datos['trigger_category_id'] : null,
            'priority' => (int) ($datos['priority'] ?? 0),
            'is_active' => (bool) ($datos['is_active'] ?? true),
        ];
        $paraOferta = fn ($id) => $base + ($porCategoria
            ? ['offer_product_id' => null, 'offer_category_id' => (int) $id, 'discount_percent' => $descuento]
            : ['offer_product_id' => $id, 'offer_category_id' => null, 'discount_percent' => $descuentos[$id] ?? $descuento]);

        return DB::transaction(function () use ($regla, $nuevas, $existentes, $paraOferta, $porCategoria, $nombreDe) {
            $pendientes = $nuevas;

            if ($regla) {
                // La editada se queda con su oferta si sigue elegida: así su id (y lo que
                // apunte a ella) sigue significando lo mismo.
                $actual = $porCategoria ? $regla->offer_category_id : $regla->offer_product_id;
                $propia = $pendientes->first(fn ($id) => $id == $actual) ?? $pendientes->first();
                $regla->update($paraOferta($propia));
                $pendientes = $pendientes->reject(fn ($id) => $id === $propia)->values();
            }

            $creadas = $pendientes->map(fn ($id) => UpsellRule::create($paraOferta($id)));

            return [
                'regla' => $regla ?? $creadas->first(),
                'creadas' => $pendientes->map($nombreDe)->values(),
                'omitidas' => $existentes->map($nombreDe)->values(),
            ];
        });
    }

    /** @return Collection<int, string|int> */
    private function ofertasDelFormulario(array $datos): Collection
    {
        if (filled($datos['offer_category_id'] ?? null)) {
            return collect([(int) $datos['offer_category_id']]);
        }

        return collect($datos['offer_product_ids'] ?? [])->filter()->unique()->values();
    }

    private function tieneDisparador(array $datos): bool
    {
        return filled($datos['trigger_product_id'] ?? null) || filled($datos['trigger_category_id'] ?? null);
    }

    /**
     * Reglas con exactamente este disparador. Un disparador por producto y otro por
     * categoría nunca coinciden, aunque el producto sea de esa categoría.
     *
     * @param  Builder<UpsellRule>  $consulta
     * @return Builder<UpsellRule>
     */
    private function conDisparador(Builder $consulta, array $datos): Builder
    {
        return filled($datos['trigger_category_id'] ?? null)
            ? $consulta->where('trigger_category_id', (int) $datos['trigger_category_id'])->whereNull('trigger_product_id')
            : $consulta->where('trigger_product_id', $datos['trigger_product_id'])->whereNull('trigger_category_id');
    }

    /** @return Closure(string|int): string */
    private function nombresDeOfertas(Collection $ofertas, bool $porCategoria): Closure
    {
        $nombres = $porCategoria
            ? Category::whereIn('id', $ofertas)->pluck('name', 'id')->map(fn ($n) => 'la categoría '.$n)
            : Product::whereIn('id', $ofertas)->pluck('name', 'id');

        return fn ($id) => $nombres[$id] ?? (string) $id;
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
