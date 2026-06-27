<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * Integración con MercadoPago (Checkout Pro / Preferences) vía API REST.
 */
class MercadoPagoService
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private function client()
    {
        return Http::withToken((string) config('services.mercadopago.access_token'))
            ->acceptJson()
            ->baseUrl(self::BASE_URL);
    }

    /**
     * Crea una preferencia de pago y devuelve el init_point (URL de checkout).
     *
     * @return array{id: string, init_point: ?string, raw: array}
     */
    public function createPreference(Order $order): array
    {
        $frontend = rtrim((string) config('services.frontend_url'), '/');

        $items = $order->items->map(fn ($item) => [
            'title' => optional(optional($item->variant)->product)->name ?? "Pedido {$order->order_number}",
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'currency_id' => config('services.mercadopago.currency', 'BOB'),
        ])->values()->all();

        $response = $this->client()->post('/checkout/preferences', [
            'items' => $items ?: [[
                'title' => "Pedido {$order->order_number}",
                'quantity' => 1,
                'unit_price' => (float) $order->total,
                'currency_id' => config('services.mercadopago.currency', 'BOB'),
            ]],
            'external_reference' => $order->id,
            'payer' => [
                'email' => $order->guest_email ?: optional($order->user)->email,
            ],
            'back_urls' => [
                'success' => "{$frontend}/checkout/mercadopago/success?order={$order->id}",
                'failure' => "{$frontend}/checkout/mercadopago/failure?order={$order->id}",
                'pending' => "{$frontend}/checkout/mercadopago/pending?order={$order->id}",
            ],
            'auto_return' => 'approved',
            'notification_url' => url('/api/v1/webhooks/mercadopago'),
        ])->throw()->json();

        return [
            'id' => $response['id'],
            'init_point' => $response['init_point'] ?? null,
            'raw' => $response,
        ];
    }

    /**
     * Consulta el detalle de un pago de MercadoPago por su id.
     */
    public function getPayment(string $paymentId): array
    {
        return $this->client()
            ->get("/v1/payments/{$paymentId}")
            ->throw()
            ->json();
    }

    /**
     * Verifica la firma del webhook de MercadoPago (header x-signature).
     */
    public function verifyWebhook(array $headers, array $query): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');
        $signature = $headers['x-signature'] ?? null;
        $requestId = $headers['x-request-id'] ?? null;

        if (! $secret || ! $signature) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[trim((string) $key)] = trim((string) $value);
        }

        $ts = $parts['ts'] ?? null;
        $hash = $parts['v1'] ?? null;
        $dataId = $query['data.id'] ?? $query['id'] ?? '';

        if (! $ts || ! $hash) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }
}
