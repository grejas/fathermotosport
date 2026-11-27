<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * Mercado Pago México (Checkout Pro / Preferences) vía API REST.
 *
 * La cuenta es mexicana y solo cobra en MXN. Los precios de la tienda están en USD, así
 * que al iniciar el pago se fija el total en pesos con la cotización del día
 * (ManualPaymentService::usdPorPesoMexicano) y se guarda en el pedido
 * (payment_currency / payment_amount / payment_rate). El webhook compara lo cobrado
 * contra ese monto antes de marcar el pedido como pagado.
 */
class MercadoPagoService
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public const MONEDA = 'MXN';

    /** ¿Hay Access Token cargado en el .env? Sin él, el botón no se ofrece. */
    public static function configurado(): bool
    {
        return filled(config('services.mercadopago.access_token'));
    }

    private function client()
    {
        return Http::withToken((string) config('services.mercadopago.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->baseUrl(self::BASE_URL);
    }

    /**
     * Crea una preferencia de pago por el total del pedido en MXN (ya fijado en el
     * pedido) y devuelve el init_point (URL del checkout de Mercado Pago).
     *
     * Va como una sola línea "Pedido FMS-…": el total ya incluye envío y cupón, y
     * convertir línea por línea podría no sumar exacto por el redondeo.
     *
     * @return array{id: string, init_point: ?string}
     */
    public function createPreference(Order $order): array
    {
        $exito = $this->urlDeExito($order);
        $reintento = $order->urlParaPagar();

        $response = $this->client()
            // Idempotencia: el mismo pedido con el mismo monto no crea dos preferencias.
            ->withHeaders(['X-Idempotency-Key' => "fms-{$order->id}-".str_replace('.', '', (string) $order->payment_amount)])
            ->post('/checkout/preferences', [
                'items' => [[
                    'id' => $order->order_number,
                    'title' => "Pedido {$order->order_number} · FatherMotoSport",
                    'quantity' => 1,
                    'unit_price' => (float) $order->payment_amount,
                    'currency_id' => self::MONEDA,
                ]],
                'external_reference' => $order->id,
                'payer' => array_filter([
                    'email' => $order->emailDelCliente(),
                ]),
                'back_urls' => [
                    'success' => $exito,
                    'failure' => $reintento,
                    'pending' => $exito,
                ],
                'auto_return' => 'approved',
                'statement_descriptor' => 'FATHERMOTOSPORT',
                'notification_url' => url('/api/v1/webhooks/mercadopago'),
            ])->throw()->json();

        return [
            'id' => (string) $response['id'],
            'init_point' => $response['init_point'] ?? null,
        ];
    }

    /** Consulta el detalle de un pago de Mercado Pago por su id. */
    public function getPayment(string $paymentId): array
    {
        return $this->client()
            ->get("/v1/payments/{$paymentId}")
            ->throw()
            ->json();
    }

    /**
     * ¿Lo cobrado cubre el pedido? Misma moneda (MXN) y un monto que no quede corto
     * (se toleran centavos de redondeo).
     */
    public static function montoCubre(array $detalle, Order $order): bool
    {
        $esperado = (float) $order->payment_amount;

        return $order->payment_currency === self::MONEDA
            && ($detalle['currency_id'] ?? null) === self::MONEDA
            && $esperado > 0
            && (float) ($detalle['transaction_amount'] ?? 0) >= $esperado - 0.01;
    }

    /**
     * Verifica la firma del webhook de Mercado Pago (header x-signature).
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
        // Mercado Pago firma el id en minúsculas cuando es alfanumérico.
        $dataId = strtolower((string) ($query['data.id'] ?? $query['data_id'] ?? $query['id'] ?? ''));

        if (! $ts || ! $hash) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    /** Página de "gracias" del pedido, en el idioma del cliente y con su token. */
    private function urlDeExito(Order $order): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $prefijo = $order->idioma() === Order::LOCALES[0] ? '' : '/'.$order->idioma();

        return "{$base}{$prefijo}/checkout/success?".http_build_query([
            'order' => $order->id,
            'number' => $order->order_number,
            't' => $order->access_token,
        ]);
    }
}
