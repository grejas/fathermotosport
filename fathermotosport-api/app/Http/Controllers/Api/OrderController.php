<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\EmailService;
use App\Services\OrderService;
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
        $order = $this->orders->createOrder($request->validated(), $request->user());

        // Confirmación por email a todos los compradores (guest o registrados).
        $this->emails->sendOrderConfirmation($order);

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
     * Detalle de un pedido. Guests pueden consultar con su guest_email; auth ve los propios.
     */
    public function show(Request $request, string $id): OrderResource
    {
        $order = Order::with(['items.variant.product', 'address', 'payments', 'shipment'])
            ->findOrFail($id);

        $user = $request->user();
        if ($user && $order->user_id && $order->user_id !== $user->id && ! $user->isAdmin() && ! $user->isEmpleado()) {
            abort(403, 'No puedes ver este pedido.');
        }

        return new OrderResource($order);
    }

    /**
     * Mis pedidos (solo autenticado).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::where('user_id', $request->user()->id)
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
