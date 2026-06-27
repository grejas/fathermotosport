<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Integración con la API REST de PayPal (Orders v2) usando el cliente HTTP de Laravel.
 */
class PaypalService
{
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.secret')
            )
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ])
            ->throw();

        return $response->json('access_token');
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->baseUrl($this->baseUrl());
    }

    /**
     * Crea una orden en PayPal y devuelve el id + approval_url.
     *
     * @return array{id: string, approval_url: ?string, raw: array}
     */
    public function createOrder(Order $order): array
    {
        $frontend = rtrim((string) config('services.frontend_url'), '/');

        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'amount' => [
                    'currency_code' => config('services.paypal.currency', 'USD'),
                    'value' => number_format((float) $order->total, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'FatherMotoSport',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => "{$frontend}/checkout/paypal/success?order={$order->id}",
                'cancel_url' => "{$frontend}/checkout/paypal/cancel?order={$order->id}",
            ],
        ])->throw()->json();

        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'id' => $response['id'],
            'approval_url' => $approval,
            'raw' => $response,
        ];
    }

    /**
     * Captura el pago de una orden previamente aprobada.
     */
    public function captureOrder(string $paypalOrderId): array
    {
        return $this->client()
            ->post("/v2/checkout/orders/{$paypalOrderId}/capture")
            ->throw()
            ->json();
    }

    /**
     * Verifica la firma de un webhook de PayPal.
     */
    public function verifyWebhook(array $headers, array $payload): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId) {
            return false;
        }

        $response = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'] ?? null,
            'cert_url' => $headers['paypal-cert-url'] ?? null,
            'transmission_id' => $headers['paypal-transmission-id'] ?? null,
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
            'transmission_time' => $headers['paypal-transmission-time'] ?? null,
            'webhook_id' => $webhookId,
            'webhook_event' => $payload,
        ])->json();

        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }
}
