<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreUpsellRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\UpsellRule;
use App\Services\UpsellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Venta cruzada post-compra. Las rutas van autorizadas con el access_token del
 * pedido (o la sesión del dueño), igual que los pagos: el comprador invitado no tiene
 * cuenta y este es su único modo de identificarse.
 */
class UpsellController extends Controller
{
    public function __construct(private UpsellService $upsell)
    {
    }

    /** Ofertas que corresponden a un pedido pagado. Lista vacía si no hay ninguna. */
    public function index(Request $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $this->autorizar($request, $order);

        $reglas = $this->upsell->ofertasPara($order);

        // La ventana arranca la primera vez que el cliente ve algo para comprar; un
        // pedido sin ofertas no la consume.
        if ($reglas->isNotEmpty()) {
            $this->upsell->registrarOfertaMostrada($order);
        }

        $ofertas = $reglas->map(fn (UpsellRule $regla) => [
            'rule_id' => $regla->id,
            'discount_percent' => $regla->discount_percent,
            'price' => number_format($regla->precioBase(), 2, '.', ''),
            'discounted_price' => number_format($regla->precioConDescuento(), 2, '.', ''),
            'product' => [
                'id' => $regla->offerProduct->id,
                'name' => $regla->offerProduct->name,
                'slug' => $regla->offerProduct->slug,
                'primary_image' => $regla->offerProduct->primary_image,
                // Solo las que se pueden comprar: así el selector de talla nunca
                // ofrece algo agotado.
                'variants' => $this->upsell->variantesDisponibles($regla)
                    ->map(fn (ProductVariant $v) => [
                        'id' => $v->id,
                        'size' => $v->size,
                        'stock' => $v->stock,
                    ])->values(),
            ],
        ])->values();

        return response()->json(['offers' => $ofertas]);
    }

    /** Crea (o rehace) el pedido de venta cruzada con lo que el cliente eligió. */
    public function store(StoreUpsellRequest $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $this->autorizar($request, $order);

        $upsell = $this->upsell->crearPedido($order, $request->validated()['items']);

        return response()->json([
            'message' => 'Pedido creado correctamente.',
            'order' => [
                'id' => $upsell->id,
                'order_number' => $upsell->order_number,
                'access_token' => $upsell->access_token,
                'subtotal' => $upsell->subtotal,
                'discount' => $upsell->discount,
                'total' => $upsell->total,
            ],
        ], 201);
    }

    /**
     * El cliente cerró el modal sin comprar: la oferta deja de existir para este
     * pedido. Repetirlo no cambia nada, así un doble clic o un reintento no fallan.
     */
    public function dismiss(Request $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $this->autorizar($request, $order);

        $this->upsell->descartar($order);

        return response()->json(['dismissed' => true]);
    }

    private function autorizar(Request $request, Order $order): void
    {
        if (! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            abort(403, 'No puedes ver este pedido.');
        }
    }
}
