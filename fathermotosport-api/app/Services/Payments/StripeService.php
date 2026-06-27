<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * Integración con Stripe (PaymentIntents) usando la API REST y el cliente HTTP de Laravel.
 */
class StripeService
{
    private const BASE_URL = 'https://api.stripe.com';

    private function client()
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->baseUrl(self::BASE_URL);
    }

    /**
     * Crea un PaymentIntent y devuelve el client_secret para el frontend.
     *
     * @return array{id: string, client_secret: string, raw: array}
     */
    public function createPaymentIntent(Order $order): array
    {
        // Stripe maneja montos en la unidad mínima (centavos).
        $amount = (int) round((float) $order->total * 100);

        $response = $this->client()->post('/v1/payment_intents', [
            'amount' => $amount,
            'currency' => config('services.stripe.currency', 'usd'),
            'description' => "Pedido {$order->order_number} - FatherMotoSport",
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'automatic_payment_methods' => ['enabled' => 'true'],
        ])->throw()->json();

        return [
            'id' => $response['id'],
            'client_secret' => $response['client_secret'],
            'raw' => $response,
        ];
    }

    /**
     * Recupera el estado de un PaymentIntent para confirmar el pago.
     */
    public function confirmPayment(string $paymentIntentId): array
    {
        return $this->client()
            ->get("/v1/payment_intents/{$paymentIntentId}")
            ->throw()
            ->json();
    }

    /**
     * Verifica la firma de un webhook de Stripe (header Stripe-Signature) usando STRIPE_WEBHOOK_SECRET.
     */
    public function verifyWebhook(string $payload, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if (! $secret || ! $signatureHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Rechaza eventos con más de 5 minutos de antigüedad (replay protection).
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
