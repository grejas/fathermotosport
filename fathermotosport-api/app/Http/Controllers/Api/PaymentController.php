<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\MercadoPagoService;
use App\Services\Payments\PaypalService;
use App\Services\Payments\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaypalService $paypal,
        private StripeService $stripe,
        private MercadoPagoService $mercadopago,
    ) {
    }

    // ───────────────────────── PayPal ─────────────────────────

    public function paypalCreate(Request $request): JsonResponse
    {
        $order = Order::findOrFail($request->validate(['order_id' => 'required|uuid'])['order_id']);

        $paypalOrder = $this->paypal->createOrder($order);

        $this->recordPayment($order, 'paypal', $paypalOrder['id'], 'USD');

        return response()->json([
            'paypal_order_id' => $paypalOrder['id'],
            'approval_url' => $paypalOrder['approval_url'],
        ]);
    }

    public function paypalCapture(string $orderId): JsonResponse
    {
        $result = $this->paypal->captureOrder($orderId);
        $status = $result['status'] ?? null;

        $payment = Payment::where('transaction_id', $orderId)->first();

        if ($status === 'COMPLETED' && $payment) {
            $this->markPaid($payment, $result);
        }

        return response()->json([
            'status' => $status,
            'order' => $payment?->order?->fresh(),
        ]);
    }

    // ───────────────────────── Stripe ─────────────────────────

    public function stripeIntent(Request $request): JsonResponse
    {
        $order = Order::findOrFail($request->validate(['order_id' => 'required|uuid'])['order_id']);

        $intent = $this->stripe->createPaymentIntent($order);

        $this->recordPayment($order, 'stripe', $intent['id'], strtoupper((string) config('services.stripe.currency', 'usd')));

        return response()->json([
            'client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['id'],
        ]);
    }

    public function stripeConfirm(Request $request): JsonResponse
    {
        $data = $request->validate(['payment_intent_id' => 'required|string']);

        $intent = $this->stripe->confirmPayment($data['payment_intent_id']);
        $payment = Payment::where('transaction_id', $data['payment_intent_id'])->first();

        if (($intent['status'] ?? null) === 'succeeded' && $payment) {
            $this->markPaid($payment, $intent);
        }

        return response()->json([
            'status' => $intent['status'] ?? null,
            'order' => $payment?->order?->fresh(),
        ]);
    }

    // ─────────────────────── MercadoPago ───────────────────────

    public function mercadopagoCreate(Request $request): JsonResponse
    {
        $order = Order::with('items')->findOrFail($request->validate(['order_id' => 'required|uuid'])['order_id']);

        $preference = $this->mercadopago->createPreference($order);

        $this->recordPayment($order, 'mercadopago', $preference['id'], strtoupper((string) config('services.mercadopago.currency', 'BOB')));

        return response()->json([
            'preference_id' => $preference['id'],
            'init_point' => $preference['init_point'],
        ]);
    }

    // ───────────────────────── Webhooks ─────────────────────────

    public function paypalWebhook(Request $request): JsonResponse
    {
        if (! $this->paypal->verifyWebhook($this->flatHeaders($request), $request->all())) {
            Log::warning('Webhook PayPal con firma inválida.');

            return response()->json(['message' => 'Firma inválida.'], 400);
        }

        $event = $request->input('event_type');
        $captureId = $request->input('resource.id');
        $reference = $request->input('resource.supplementary_data.related_ids.order_id') ?? $request->input('resource.id');

        if (in_array($event, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            $payment = Payment::where('transaction_id', $reference)->first();
            if ($payment) {
                $this->markPaid($payment, $request->all());
            }
        }

        return response()->json(['received' => true]);
    }

    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $this->stripe->verifyWebhook($payload, $signature)) {
            Log::warning('Webhook Stripe con firma inválida.');

            return response()->json(['message' => 'Firma inválida.'], 400);
        }

        $event = $request->input('type');
        $intentId = $request->input('data.object.id');

        if ($event === 'payment_intent.succeeded') {
            $payment = Payment::where('transaction_id', $intentId)->first();
            if ($payment) {
                $this->markPaid($payment, $request->input('data.object', []));
            }
        }

        return response()->json(['received' => true]);
    }

    public function mercadopagoWebhook(Request $request): JsonResponse
    {
        if (! $this->mercadopago->verifyWebhook($this->flatHeaders($request), $request->query())) {
            Log::warning('Webhook MercadoPago con firma inválida.');

            return response()->json(['message' => 'Firma inválida.'], 400);
        }

        $paymentId = $request->input('data.id') ?? $request->query('data.id');

        if ($paymentId) {
            $detail = $this->mercadopago->getPayment((string) $paymentId);
            $orderId = $detail['external_reference'] ?? null;

            if (($detail['status'] ?? null) === 'approved' && $orderId) {
                $payment = Payment::where('order_id', $orderId)->latest()->first();
                if ($payment) {
                    $this->markPaid($payment, $detail);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function recordPayment(Order $order, string $provider, string $transactionId, string $currency): Payment
    {
        return Payment::updateOrCreate(
            ['transaction_id' => $transactionId],
            [
                'order_id' => $order->id,
                'provider' => $provider,
                'currency' => $currency,
                'amount' => $order->total,
                'status' => 'pending',
            ]
        );
    }

    private function markPaid(Payment $payment, array $gatewayResponse): void
    {
        $payment->update([
            'status' => 'approved',
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
        ]);

        $payment->order?->update([
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);
    }

    private function flatHeaders(Request $request): array
    {
        $flat = [];
        foreach ($request->headers->all() as $key => $values) {
            $flat[strtolower($key)] = is_array($values) ? ($values[0] ?? null) : $values;
        }

        return $flat;
    }
}
