<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Monto del pedido en la unidad mínima de la moneda (centavos), como lo espera
     * Stripe. Es público porque al reutilizar un intent hay que comparar el monto
     * que Stripe tiene guardado contra el del pedido, con la misma regla de redondeo.
     */
    public function montoEnCentavos(Order $order): int
    {
        return (int) round((float) $order->total * 100);
    }

    /**
     * Crea un PaymentIntent y devuelve el client_secret para el frontend.
     *
     * @return array{id: string, client_secret: string, raw: array}
     */
    /**
     * @param  string|null  $modo  'card' = solo tarjeta (formulario de tarjeta, carga más
     *                             rápido); 'wallet' o null = métodos automáticos (Apple
     *                             Pay, Google Pay, Amazon Pay y tarjeta).
     */
    public function createPaymentIntent(Order $order, bool $guardarTarjeta = false, ?string $modo = null): array
    {
        $datos = [
            'amount' => $this->montoEnCentavos($order),
            'currency' => config('services.stripe.currency', 'usd'),
            'description' => "Pedido {$order->order_number} - FatherMotoSport",
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        if ($modo === 'card') {
            $datos['payment_method_types'] = ['card'];
        } else {
            $datos['automatic_payment_methods'] = ['enabled' => 'true'];
        }

        // Pedido normal, y solo si la web lo pide (es la que muestra el aviso al cliente):
        // la tarjeta queda guardada en un Customer de Stripe para que la venta cruzada se
        // cobre en un toque, sin volver a escribirla. Solo la tarjeta (incluye Apple Pay
        // y Google Pay): ponerlo a nivel del intent ocultaría los métodos que no admiten
        // guardarse. Si Stripe no crea el Customer, se cobra igual, sin guardar nada.
        if ($guardarTarjeta && ! $order->esUpsell() && ($cliente = $this->crearCliente($order))) {
            $datos['customer'] = $cliente;
            $datos['payment_method_options'] = ['card' => ['setup_future_usage' => 'on_session']];
        }

        $response = $this->client()->post('/v1/payment_intents', $datos)->throw()->json();

        return [
            'id' => $response['id'],
            'client_secret' => $response['client_secret'],
            'raw' => $response,
        ];
    }

    /** Customer de Stripe para el comprador del pedido; null si Stripe lo rechaza. */
    private function crearCliente(Order $order): ?string
    {
        try {
            $cliente = $this->client()->post('/v1/customers', array_filter([
                'email' => $order->emailDelCliente(),
                'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number],
            ]))->throw()->json();
        } catch (RequestException $e) {
            Log::warning('Stripe no creó el Customer: el pago sigue sin guardar la tarjeta.', [
                'order' => $order->id,
                'status' => $e->response->status(),
            ]);

            return null;
        }

        $id = $cliente['id'] ?? null;

        return is_string($id) && str_starts_with($id, 'cus_') ? $id : null;
    }

    /**
     * Tarjeta guardada del pago aprobado de un pedido (el último), o null si se pagó
     * con otro medio o la tarjeta no quedó guardada.
     *
     * @return array{customer: string, payment_method: string, brand: string|null, last4: string|null}|null
     */
    public function tarjetaDelPedido(Order $order): ?array
    {
        $pago = Payment::where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->where('status', 'approved')
            ->latest()
            ->first();

        return $pago ? $this->tarjetaGuardada($pago->transaction_id) : null;
    }

    /**
     * Tarjeta con la que se pagó un pedido, si quedó guardada y se puede volver a usar.
     * Sale del PaymentIntent que lo cobró (la fuente de verdad es Stripe, no nuestra
     * base): Customer, PaymentMethod, marca y últimos 4 dígitos.
     *
     * @return array{customer: string, payment_method: string, brand: string|null, last4: string|null}|null
     */
    public function tarjetaGuardada(string $paymentIntentId): ?array
    {
        try {
            $intent = $this->client()
                ->get("/v1/payment_intents/{$paymentIntentId}", ['expand' => ['payment_method']])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            return null;
        }

        $customer = $intent['customer'] ?? null;
        $metodo = $intent['payment_method'] ?? null;
        $guardada = ($intent['payment_method_options']['card']['setup_future_usage'] ?? null) !== null;

        if (($intent['status'] ?? null) !== 'succeeded' || ! $guardada || ! is_string($customer)
            || ! is_array($metodo) || ($metodo['type'] ?? null) !== 'card' || ($metodo['customer'] ?? null) !== $customer) {
            return null;
        }

        return [
            'customer' => $customer,
            'payment_method' => (string) $metodo['id'],
            'brand' => $metodo['card']['brand'] ?? null,
            'last4' => $metodo['card']['last4'] ?? null,
        ];
    }

    /**
     * Cobra un pedido con una tarjeta ya guardada, con el cliente presente (no es un
     * cobro en segundo plano). Si el banco pide 3-D Secure, el intent vuelve con
     * status requires_action y el navegador completa la verificación con su
     * client_secret.
     *
     * @param  array{customer: string, payment_method: string}  $tarjeta
     * @return array{id: string, client_secret: string, status: string, raw: array}
     */
    public function cobrarConTarjetaGuardada(Order $order, array $tarjeta): array
    {
        // Clave de idempotencia por pedido y monto: un doble toque o un reintento por
        // red no cobran dos veces; si el cliente cambia lo elegido, el monto cambia y
        // es un cobro nuevo.
        $clave = "upsell-{$order->id}-{$this->montoEnCentavos($order)}";

        $response = $this->client()->withHeaders(['Idempotency-Key' => $clave])->post('/v1/payment_intents', [
            'amount' => $this->montoEnCentavos($order),
            'currency' => config('services.stripe.currency', 'usd'),
            'description' => "Pedido {$order->order_number} - FatherMotoSport",
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'customer' => $tarjeta['customer'],
            'payment_method' => $tarjeta['payment_method'],
            // Solo tarjeta: así confirmar en el servidor no exige una return_url.
            'payment_method_types' => ['card'],
            'confirm' => 'true',
        ])->throw()->json();

        return [
            'id' => $response['id'],
            'client_secret' => $response['client_secret'],
            'status' => (string) ($response['status'] ?? ''),
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
