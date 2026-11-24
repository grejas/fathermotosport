<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
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

        // Esta cuenta de Stripe ya no acepta payment_method_types: los métodos salen de una
        // configuración. Para "solo tarjeta" se usa una propia (ver configuracionSoloTarjeta).
        $datos['automatic_payment_methods'] = ['enabled' => 'true'];
        $soloTarjeta = $modo === 'card' ? $this->configuracionSoloTarjeta() : null;
        if ($soloTarjeta) {
            $datos['payment_method_configuration'] = $soloTarjeta;
        }

        // Pedido normal, y solo si la web lo pide (es la que muestra el aviso al cliente):
        // la tarjeta queda guardada en un Customer de Stripe para que la venta cruzada se
        // cobre en un toque, sin volver a escribirla. Solo la tarjeta (incluye Apple Pay
        // y Google Pay): ponerlo a nivel del intent ocultaría los métodos que no admiten
        // guardarse. Si Stripe no crea el Customer, se cobra igual, sin guardar nada.
        if ($guardarTarjeta && ! $order->esUpsell() && ($cliente = $this->crearCliente($order))) {
            $datos['customer'] = $cliente;
            // Solo tarjeta: a nivel del intent (así coincide con el formulario que la web
            // ya precargó). Con métodos automáticos, solo para la tarjeta.
            if ($soloTarjeta) {
                $datos['setup_future_usage'] = 'on_session';
            } else {
                $datos['payment_method_options'] = ['card' => ['setup_future_usage' => 'on_session']];
            }
        }

        $response = $this->client()->post('/v1/payment_intents', $datos)->throw()->json();

        return [
            'id' => $response['id'],
            'client_secret' => $response['client_secret'],
            'raw' => $response,
        ];
    }

    /** Nombre de la configuración de métodos de pago "solo tarjeta" que crea la tienda. */
    public const CONFIG_SOLO_TARJETA = 'FMS solo tarjeta';

    /**
     * Id de una configuración de métodos de pago con SOLO tarjeta (sin Apple Pay, Google
     * Pay, Link, Amazon Pay ni bancos). El formulario de tarjeta carga mucho más rápido
     * así. Se busca por nombre y, si no existe, se crea copiando la de la cuenta con todo
     * apagado salvo la tarjeta. Se recuerda un día. Null si Stripe no lo permite: entonces
     * se cobra con la configuración normal.
     */
    public function configuracionSoloTarjeta(): ?string
    {
        $id = Cache::get('stripe_config_solo_tarjeta');
        if (is_string($id) && $id !== '') {
            return $id;
        }

        try {
            $lista = $this->client()->get('/v1/payment_method_configurations', ['limit' => 100])->throw()->json('data') ?? [];

            $existente = collect($lista)->first(fn ($c) => ($c['name'] ?? null) === self::CONFIG_SOLO_TARJETA && ($c['active'] ?? false));
            $id = $existente['id'] ?? null;

            if (! $id) {
                $base = collect($lista)->first(fn ($c) => $c['is_default'] ?? false) ?? [];
                $datos = ['name' => self::CONFIG_SOLO_TARJETA, 'card' => ['display_preference' => ['preference' => 'on']]];

                foreach ($base as $metodo => $valor) {
                    if ($metodo !== 'card' && is_array($valor) && isset($valor['display_preference'])) {
                        $datos[$metodo] = ['display_preference' => ['preference' => 'off']];
                    }
                }

                $id = $this->client()->post('/v1/payment_method_configurations', $datos)->throw()->json('id');
            }
        } catch (RequestException $e) {
            Log::warning('Stripe: no se pudo obtener la configuración "solo tarjeta"; se usa la normal.', [
                'status' => $e->response->status(),
                'error' => $e->response->json('error.message'),
            ]);

            return null;
        }

        if (! is_string($id) || ! str_starts_with($id, 'pmc_')) {
            return null;
        }

        Cache::put('stripe_config_solo_tarjeta', $id, now()->addDay());

        return $id;
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
        $guardada = ($intent['payment_method_options']['card']['setup_future_usage'] ?? null) !== null
            || ($intent['setup_future_usage'] ?? null) !== null;

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
            // Sin redirecciones: así confirmar en el servidor no exige una return_url (y esta
            // cuenta ya no acepta payment_method_types).
            'automatic_payment_methods' => ['enabled' => 'true', 'allow_redirects' => 'never'],
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
