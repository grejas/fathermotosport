<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmedMail;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Payments\MercadoPagoService;
use App\Services\Payments\PaypalService;
use App\Services\Payments\StripeService;
use App\Support\Countries;
use App\Support\ShippingCountries;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        // Solo el comprador (token del pedido o sesión con permiso) puede iniciar el pago.
        if (! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            abort(403, 'No puedes pagar este pedido.');
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Este pedido ya fue pagado.'], 409);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return response()->json(['message' => 'Este pedido ya no se puede pagar.'], 409);
        }

        // Reutiliza la orden de PayPal creada hace menos de 30 minutos: evita generar
        // una nueva (y un cobro doble) si el cliente recarga o vuelve atrás.
        $pendiente = Payment::where('order_id', $order->id)
            ->where('provider', 'paypal')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if ($pendiente && filled($pendiente->gateway_response['approval_url'] ?? null)) {
            return response()->json([
                'paypal_order_id' => $pendiente->transaction_id,
                'approval_url' => $pendiente->gateway_response['approval_url'],
                'reused' => true,
            ]);
        }

        try {
            $paypalOrder = $this->paypal->createOrder($order);
        } catch (RequestException $e) {
            Log::error('PayPal rechazó la creación de la orden.', [
                'order' => $order->id,
                'status' => $e->response->status(),
                'body' => $e->response->json(),
            ]);

            return response()->json([
                'message' => 'No pudimos iniciar el pago con PayPal. Intentá de nuevo o contactanos por WhatsApp.',
            ], 502);
        }

        $payment = $this->recordPayment($order, 'paypal', $paypalOrder['id'], 'USD');
        $payment->update(['gateway_response' => ['approval_url' => $paypalOrder['approval_url']]]);

        return response()->json([
            'paypal_order_id' => $paypalOrder['id'],
            'approval_url' => $paypalOrder['approval_url'],
        ]);
    }

    /**
     * $paypalOrderId es el id de la orden de PayPal (el ?token= del retorno).
     * Devuelve solo el estado del pedido: nunca el pedido completo con datos personales.
     */
    public function paypalCapture(Request $request, string $paypalOrderId): JsonResponse
    {
        $payment = Payment::with('order')->where('transaction_id', $paypalOrderId)->first();

        if (! $payment) {
            // Sin esta línea, un 404 acá no dejaba ningún rastro en el log.
            Log::warning('Captura de PayPal para una orden que no existe en payments.', [
                'paypal_order' => $paypalOrderId,
            ]);

            abort(404, 'No encontramos el pago de PayPal.');
        }

        $order = $payment->order;

        if (! $order || ! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            Log::warning('Captura de PayPal rechazada por falta de autorización.', [
                'paypal_order' => $paypalOrderId,
                'pedido' => $order?->order_number,
                'con_token' => filled(Order::tokenFromRequest($request)),
                'con_sesion' => (bool) $request->user('sanctum'),
            ]);

            abort(403, 'No puedes confirmar el pago de este pedido.');
        }

        // Idempotente: si ya se capturó (por el retorno o por el webhook), no se repite.
        if ($order->payment_status === 'paid') {
            return $this->estadoDelPago('COMPLETED', $order->fresh('address'));
        }

        try {
            $result = $this->paypal->captureOrder($paypalOrderId);
        } catch (RequestException $e) {
            // Ej. el cliente volvió sin aprobar el pago (ORDER_NOT_APPROVED).
            Log::warning('PayPal rechazó la captura.', [
                'order' => $order->id,
                'paypal_order' => $paypalOrderId,
                'status' => $e->response->status(),
                'body' => $e->response->json(),
            ]);

            return response()->json([
                'status' => data_get($e->response->json(), 'details.0.issue', 'CAPTURE_FAILED'),
                'message' => 'PayPal no pudo confirmar el pago. Tu pedido quedó pendiente.',
                // Para que la pantalla de error muestre "FMS-0001" y no el UUID.
                'order_number' => $order->order_number,
            ], 422);
        }

        $status = $result['status'] ?? null;

        if ($status === 'COMPLETED') {
            if (! $this->montoCoincide($result, $order)) {
                Log::error('Captura de PayPal con monto distinto al del pedido.', [
                    'order' => $order->id,
                    'esperado' => $order->total,
                    'paypal' => $result,
                ]);

                return response()->json([
                    'status' => $status,
                    'message' => 'El monto cobrado no coincide con el del pedido. Contactanos por WhatsApp.',
                    'order_number' => $order->order_number,
                ], 409);
            }

            // Express: los datos del comprador llegan recién ahora. Se completan ANTES
            // de markPaid(), porque si no el pedido no tendría email al que enviar
            // la confirmación y el envío se saltaría en silencio.
            if ($order->esperaDatosDePaypal()) {
                $this->completarPedidoConDatosDePaypal($order, $result);
                $payment->setRelation('order', $order->fresh('address'));
            }

            $this->markPaid($payment, $result);
        } else {
            // PayPal respondió 200 pero sin COMPLETED (ej. PENDING o DECLINED):
            // hasta ahora este caso tampoco quedaba en el log.
            Log::warning('Captura de PayPal sin estado COMPLETED.', [
                'pedido' => $order->order_number,
                'paypal_order' => $paypalOrderId,
                'estado' => $status,
                'respuesta' => json_encode($result),
            ]);
        }

        return $this->estadoDelPago($status, $order->fresh('address'));
    }

    /**
     * Completa un pedido Express con lo que informó PayPal: nombre, email, teléfono
     * y dirección de envío. Si el país no coincide con el que el cliente eligió en el
     * carrito, el envío cobrado puede no corresponder: queda anotado para revisión.
     */
    private function completarPedidoConDatosDePaypal(Order $order, array $result): void
    {
        $payer = data_get($result, 'payer', []);
        $shipping = data_get($result, 'purchase_units.0.shipping', []);

        $nombre = trim(data_get($shipping, 'name.full_name')
            ?: trim(data_get($payer, 'name.given_name', '').' '.data_get($payer, 'name.surname', '')));
        $email = data_get($payer, 'email_address');
        $telefono = data_get($payer, 'phone.phone_number.national_number');

        $paisIso = strtoupper((string) data_get($shipping, 'address.country_code'));
        $paisNombre = ShippingCountries::name($paisIso) ?? Countries::name($paisIso) ?? $paisIso;

        if ($order->address) {
            $order->address->update(array_filter([
                'full_name' => $nombre ?: null,
                'phone' => $telefono ?: null,
                'country' => $paisNombre ?: null,
                'state' => data_get($shipping, 'address.admin_area_1'),
                'city' => data_get($shipping, 'address.admin_area_2'),
                'postal_code' => data_get($shipping, 'address.postal_code'),
                'address_line' => trim(data_get($shipping, 'address.address_line_1', '')
                    .' '.data_get($shipping, 'address.address_line_2', '')) ?: null,
            ]));
        }

        $cambios = [];

        // Sin cuenta en el sitio: el email de PayPal es el único contacto del pedido.
        // Se deja constancia de que lo informó PayPal (y no alguien escribiéndolo en
        // un formulario): es lo que habilita vincular otros pedidos con ese email.
        if ($email && ! $order->user_id && ! $order->guest_email) {
            $cambios['guest_email'] = $email;
            $cambios['email_verificado_por'] = 'paypal';
        }

        // El país del envío cobrado vs. el que PayPal informó.
        $paisCobrado = $order->country;
        if ($paisNombre && $paisCobrado && $paisNombre !== $paisCobrado) {
            Log::warning('PayPal Express: el país de la dirección no coincide con el cobrado.', [
                'pedido' => $order->order_number,
                'cobrado' => $paisCobrado,
                'paypal' => $paisNombre,
                'envio' => $order->shipping,
            ]);

            // Mismo mecanismo que "pagado sin stock": insignia y filtro en el panel.
            $motivo = "Envío cobrado para {$paisCobrado}, pero PayPal informó {$paisNombre}. Revisar diferencia.";
        }

        if ($cambios) {
            $order->update($cambios);
        }

        // Después del update, y por el helper, para que el motivo se sume a cualquier
        // otro que ya tenga el pedido en vez de reemplazarlo.
        if (isset($motivo)) {
            $this->marcarParaAtencion($order, $motivo);
        }
    }

    /**
     * Respuesta mínima del pago: sin dirección ni teléfono.
     * Incluye nombre y email solo para ofrecer crear la cuenta en la pantalla de
     * éxito; quien recibe esto ya demostró tener el token del pedido.
     */
    private function estadoDelPago(?string $status, ?Order $order): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
                'total' => $order->total,
                // Pedido de invitado pagado: se le puede ofrecer crear una cuenta.
                'is_guest' => $order->user_id === null,
                'customer_name' => $order->address?->full_name,
                'customer_email' => $order->guest_email,
            ] : null,
        ]);
    }

    /**
     * Descuenta el stock de un pedido ya pagado y registra los movimientos de
     * inventario. Se bloquean las filas para que dos pagos simultáneos no lean el
     * mismo stock.
     *
     * Si falta stock (dos compradores por la última unidad), el cobro ya ocurrió:
     * no se puede rechazar. Se descuenta lo que haya, nunca por debajo de 0, y el
     * pedido queda marcado para que el admin lo resuelva a mano.
     */
    private function descontarStock(Order $order): void
    {
        $faltantes = [];

        DB::transaction(function () use ($order, &$faltantes) {
            $items = $order->items()->with('variant.product')->get();

            $variantes = ProductVariant::whereIn('id', $items->pluck('product_variant_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variant = $variantes->get($item->product_variant_id);

                if (! $variant) {
                    continue;
                }

                $pedido = (int) $item->quantity;
                $disponible = (int) $variant->stock;
                $aDescontar = min($pedido, max(0, $disponible));

                if ($aDescontar < $pedido) {
                    $faltantes[] = ($variant->sku ?: $variant->id)
                        .' (pedidas '.$pedido.', disponibles '.$disponible.')';
                }

                if ($aDescontar > 0) {
                    $variant->decrement('stock', $aDescontar);

                    InventoryMovement::create([
                        'product_id' => $variant->product_id,
                        'user_id' => null,
                        'type' => 'sale',
                        'quantity' => -$aDescontar,
                        'reason' => "Venta - pedido {$order->order_number}",
                    ]);
                }
            }
        });

        if ($faltantes === []) {
            return;
        }

        Log::error('Pedido pagado sin stock suficiente: requiere atención manual.', [
            'pedido' => $order->order_number,
            'order_id' => $order->id,
            'faltantes' => $faltantes,
        ]);

        $this->marcarParaAtencion(
            $order,
            'Pagado sin stock suficiente: '.implode(' · ', $faltantes)
        );
    }

    /**
     * Marca un pedido para revisión manual del admin (columna attention_reason, que
     * el panel muestra como insignia y permite filtrar) y deja el detalle en notas.
     */
    private function marcarParaAtencion(Order $order, string $motivo): void
    {
        // Un pedido puede juntar más de un motivo (revivido Y sin stock): se acumulan,
        // porque perder el primero dejaría al admin resolviendo solo la mitad.
        $previo = trim((string) $order->attention_reason);

        $order->update([
            'attention_reason' => $previo === '' ? $motivo : $previo.' · '.$motivo,
            'notes' => trim((string) $order->notes."\n⚠️ ".$motivo),
        ]);
    }

    /** Compara el importe capturado por PayPal con el total del pedido. */
    private function montoCoincide(array $result, Order $order): bool
    {
        $capturas = data_get($result, 'purchase_units.*.payments.captures.*.amount.value');
        $cobrado = array_sum(array_map('floatval', $capturas ?: []));

        return abs($cobrado - (float) $order->total) < 0.01;
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

        // Solo la captura confirma el cobro. CHECKOUT.ORDER.APPROVED significa que el
        // comprador aprobó, pero el dinero todavía no se movió: si se marcara pagado ahí,
        // un pedido podría quedar como pagado sin haber cobrado nada.
        if ($event !== 'PAYMENT.CAPTURE.COMPLETED') {
            return response()->json(['received' => true, 'handled' => false]);
        }

        // En este evento resource.id es el id de la captura; el de la orden de PayPal
        // (el que guardamos como transaction_id) viene en supplementary_data.
        $reference = $request->input('resource.supplementary_data.related_ids.order_id')
            ?? $request->input('resource.id');

        $payment = Payment::where('transaction_id', $reference)->first();

        if (! $payment) {
            Log::warning('Webhook de captura de PayPal sin pago asociado.', ['referencia' => $reference]);

            return response()->json(['received' => true, 'handled' => false]);
        }

        $this->markPaid($payment, $request->all());

        return response()->json(['received' => true, 'handled' => true]);
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

        $order = $payment->order;
        if (! $order) {
            return;
        }

        // Evitar reenviar el email (y volver a descontar stock) si ya estaba pagado:
        // el webhook y el retorno del cliente pueden llegar los dos.
        $yaPagado = $order->payment_status === 'paid';

        // El link de aprobación de PayPal vive más que la ventana del cron de abandonos,
        // así que un pedido ya cancelado puede pagarse después. El pago manda (el dinero
        // entró), pero el admin tiene que saber que revivió algo que había dado por muerto.
        $revivido = ! $yaPagado && $order->status === 'cancelled';

        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);

        if ($revivido) {
            $this->marcarParaAtencion($order, 'Pedido cancelado por falta de pago y luego pagado. Confirmar que sigue vigente.');
        }

        // El stock se descuenta acá, con el pago confirmado, no al crear el pedido.
        if (! $yaPagado) {
            $this->descontarStock($order);
        }

        // Email de confirmación a todos los compradores (guest o registrados).
        if (! $yaPagado) {
            $email = $order->guest_email ?: optional($order->user)->email;
            if ($email) {
                try {
                    Mail::to($email)->send(new OrderConfirmedMail($order->fresh(['items.variant.product', 'address', 'user'])));
                } catch (\Throwable $e) {
                    Log::error('Error enviando confirmación de pedido', ['order' => $order->id, 'error' => $e->getMessage()]);
                }
            }
        }
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
