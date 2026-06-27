<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private EmailService $emails)
    {
    }

    /**
     * Listado de pedidos con filtros (status, payment_status, country, search).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['items.variant.product', 'address', 'payments', 'user'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->country, fn ($q, $v) => $q->where('country', $v))
            ->when($request->search, fn ($q, $v) => $q->where('order_number', 'like', "%{$v}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Actualiza el estado de un pedido.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Estado actualizado.',
            'order' => new OrderResource($order->fresh(['items.variant.product', 'address'])),
        ]);
    }

    /**
     * Registra/actualiza el tracking, crea el envío y notifica al cliente por email.
     */
    public function updateTracking(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
        ]);

        $order = Order::findOrFail($id);

        $shipment = $order->shipment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'tracking_number' => $data['tracking_number'],
                'carrier' => $data['carrier'] ?? null,
                'status' => 'in_transit',
                'shipped_at' => now(),
            ]
        );

        $order->update([
            'status' => 'shipped',
            'shipping_status' => 'in_transit',
        ]);

        $this->emails->sendShippingUpdate($order, $data['tracking_number']);

        return response()->json([
            'message' => 'Tracking registrado y cliente notificado.',
            'shipment' => $shipment,
        ]);
    }
}
