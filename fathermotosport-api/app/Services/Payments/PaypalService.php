<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        try {
            $response = Http::asForm()
                ->withBasicAuth(
                    (string) config('services.paypal.client_id'),
                    (string) config('services.paypal.secret')
                )
                ->post($this->baseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw();
        } catch (RequestException $e) {
            // Caso típico: credenciales de sandbox con PAYPAL_MODE=live (o al revés) → 401.
            $this->registrar('no se pudo autenticar contra PayPal', $e, [
                'modo' => config('services.paypal.mode'),
                'client_id_termina_en' => substr((string) config('services.paypal.client_id'), -6),
            ]);

            throw $e;
        }

        return $response->json('access_token');
    }

    /**
     * Deja en el log el detalle completo de un fallo de la API de PayPal.
     * El cuerpo va sin recortar: es lo único que explica por qué PayPal rechazó algo.
     */
    private function registrar(string $que, RequestException $e, array $contexto = []): void
    {
        Log::error("PayPal: {$que}.", array_merge($contexto, [
            'http_status' => $e->response->status(),
            'respuesta' => $e->response->body(),
        ]));
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

        try {
            $response = $this->crearOrdenEnPaypal($order, $frontend);
        } catch (RequestException $e) {
            $this->registrar('falló la creación de la orden', $e, [
                'pedido' => $order->order_number,
                'total' => $order->total,
            ]);

            throw $e;
        }

        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'id' => $response['id'],
            'approval_url' => $approval,
            'raw' => $response,
        ];
    }

    /** @return array<string, mixed> */
    private function crearOrdenEnPaypal(Order $order, string $frontend): array
    {
        return $this->client()->post('/v2/checkout/orders', [
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
                // Express: PayPal pide la dirección y nos la devuelve al capturar.
                // Checkout normal: ya tenemos los datos, así que no se la pide de nuevo.
                'shipping_preference' => $order->esperaDatosDePaypal() ? 'GET_FROM_FILE' : 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                // El token va en la URL de retorno para que el comprador invitado
                // pueda confirmar el pago sin iniciar sesión.
                'return_url' => "{$frontend}/checkout/paypal/success?order={$order->id}&t={$order->access_token}",
                'cancel_url' => "{$frontend}/checkout/paypal/cancel?order={$order->id}&t={$order->access_token}",
            ],
        ])->throw()->json();
    }

    /**
     * Captura el pago de una orden previamente aprobada.
     */
    public function captureOrder(string $paypalOrderId): array
    {
        try {
            // El cuerpo vacío debe ser un JSON válido ("{}"): sin esto PayPal responde
            // MALFORMED_REQUEST_JSON y la captura nunca se completa.
            return $this->client()
                ->withBody('{}', 'application/json')
                ->post("/v2/checkout/orders/{$paypalOrderId}/capture")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $this->registrar('falló la captura', $e, ['paypal_order' => $paypalOrderId]);

            throw $e;
        }
    }

    /**
     * Consulta una orden de PayPal sin modificarla: su status dice si el cliente ya la
     * aprobó (APPROVED) o si ya se cobró (COMPLETED).
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $paypalOrderId): array
    {
        try {
            return $this->client()->get("/v2/checkout/orders/{$paypalOrderId}")->throw()->json();
        } catch (RequestException $e) {
            $this->registrar('falló la consulta de la orden', $e, ['paypal_order' => $paypalOrderId]);

            throw $e;
        }
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
