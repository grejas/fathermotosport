<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreExpressOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\EmailService;
use App\Services\OrderService;
use App\Support\ShippingCountries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private EmailService $emails,
    ) {
    }

    /**
     * Crea un pedido completo (guest checkout permitido).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->createOrder($request->validated(), $request->user('sanctum'));

        // Aviso de pedido recibido (pago pendiente). La confirmación de PAGO sale
        // desde PaymentController::markPaid(), cuando el cobro se completa.
        //
        // Con PayPal no se envía nada acá: el cliente todavía no pagó (y en el flujo
        // Express ni siquiera conocemos su email). Su único correo es el de pago
        // confirmado, desde markPaid().
        if ($order->payment_method !== 'paypal') {
            $this->emails->sendOrderReceived($order);
        }

        return response()->json([
            'message' => 'Pedido creado correctamente.',
            'order' => new OrderResource($order),
            'payment' => [
                'method' => $order->payment_method,
                'next_step' => $this->paymentHint($order->payment_method),
            ],
        ], 201);
    }

    /**
     * Pedido "PayPal Express": se crea desde el carrito con lo mínimo (items y la
     * opción de envío elegida según el país). El nombre, el email y la dirección
     * llegan después, cuando el cliente vuelve de aprobar el pago en PayPal.
     *
     * No envía ningún correo: todavía no hay a quién escribirle ni pago confirmado.
     */
    public function storeExpress(StoreExpressOrderRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $order = $this->orders->createOrder([
            'items' => $datos['items'],
            'payment_method' => 'paypal',
            'shipping_option_id' => $datos['shipping_option_id'],
            'shipping_country_code' => $datos['shipping_country_code'],
            'address' => [
                // Marcadores: se sobrescriben con los datos que devuelve PayPal.
                'full_name' => Order::DATO_PENDIENTE,
                'address_line' => Order::DATO_PENDIENTE,
                'country' => ShippingCountries::name($datos['shipping_country_code'])
                    ?? $datos['shipping_country_code'],
            ],
        ], $request->user('sanctum'));

        return response()->json([
            'message' => 'Pedido creado. Falta aprobar el pago en PayPal.',
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Detalle de un pedido. Guests pueden consultar con su guest_email; auth ve los propios.
     */
    public function show(Request $request, string $id): OrderResource
    {
        $order = Order::with(['items.variant.product', 'address', 'payments', 'shipment'])
            ->findOrFail($id);

        // Antes alcanzaba con conocer el UUID: ahora hace falta el token del pedido
        // (comprador invitado) o una sesión con permiso (dueño o staff).
        if (! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            abort(403, 'No puedes ver este pedido.');
        }

        return new OrderResource($order);
    }

    /**
     * Mis pedidos (solo autenticado).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::where('user_id', $request->user('sanctum')->id)
            ->with(['items.variant.product', 'address', 'payments'])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    private function paymentHint(string $method): string
    {
        return match ($method) {
            'paypal' => 'POST /payments/paypal/create',
            'stripe' => 'POST /payments/stripe/intent',
            'mercadopago' => 'POST /payments/mercadopago/create',
            default => '',
        };
    }
}
