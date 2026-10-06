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
            $result = $this->capturarOReleer($paypalOrderId);
        } catch (RequestException $e) {
            // Ej. el cliente volvió sin aprobar el pago (ORDER_NOT_APPROVED).
            Log::warning('PayPal rechazó la captura.', [
                'order' => $order->id,
                'paypal_order' => $paypalOrderId,
                'status' => $e->response->status(),
                'body' => $e->response->json(),
            ]);

            $motivo = data_get($e->response->json(), 'details.0.issue', 'CAPTURE_FAILED');

            // Volver sin aprobar no es un pago fallido: el cliente no llegó a pagar, y
            // eso lo cubre el aviso por abandono. Cualquier otro rechazo (fondos, tarjeta
            // de la cuenta PayPal declinada...) sí lo es.
            if ($motivo !== 'ORDER_NOT_APPROVED') {
                $this->registrarPagoFallido($order, 'paypal', $motivo);
            }

            return response()->json([
                'status' => $motivo,
                'message' => 'PayPal no pudo confirmar el pago. Tu pedido quedó pendiente.',
                // Para que la pantalla de error muestre "FMS-0001" y no el UUID.
                'order_number' => $order->order_number,
            ], 422);
        }

        $status = $result['status'] ?? null;

        if (! $this->aplicarCapturaPaypal($payment, $result)) {
            return response()->json([
                'status' => $status,
                'message' => 'El monto cobrado no coincide con el del pedido. Contactanos por WhatsApp.',
                'order_number' => $order->order_number,
            ], 409);
        }

        return $this->estadoDelPago($status, $order->fresh('address'));
    }

    /**
     * Captura la orden de PayPal. Si ya estaba capturada (el webhook de aprobación se
     * adelantó al retorno del cliente, o al revés), la relee en vez de fallar: la
     * respuesta de la consulta trae las mismas capturas, montos y datos del comprador.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException si PayPal rechaza la captura por cualquier otro motivo
     */
    private function capturarOReleer(string $paypalOrderId): array
    {
        try {
            return $this->paypal->captureOrder($paypalOrderId);
        } catch (RequestException $e) {
            if (data_get($e->response->json(), 'details.0.issue') !== 'ORDER_ALREADY_CAPTURED') {
                throw $e;
            }

            return $this->paypal->getOrder($paypalOrderId);
        }
    }

    /**
     * Aplica una captura de PayPal, venga del retorno del cliente o del webhook de
     * aprobación: mismas validaciones en los dos caminos. Devuelve false solo si se
     * cobró un monto o una moneda que no son los del pedido.
     *
     * @param  array<string, mixed>  $result
     */
    private function aplicarCapturaPaypal(Payment $payment, array $result): bool
    {
        $order = $payment->order;
        $status = $result['status'] ?? null;

        if ($status !== 'COMPLETED') {
            // PayPal respondió 200 pero sin COMPLETED (ej. PENDING o DECLINED).
            Log::warning('Captura de PayPal sin estado COMPLETED.', [
                'pedido' => $order->order_number,
                'paypal_order' => $payment->transaction_id,
                'estado' => $status,
                'respuesta' => json_encode($result),
            ]);

            // PENDING no es un fallo: PayPal todavía lo está procesando.
            if (in_array($status, ['DECLINED', 'FAILED', 'VOIDED'], true)) {
                $this->registrarPagoFallido($order, 'paypal', (string) $status);
            }

            return true;
        }

        if (! $this->montoCoincide($result, $order)) {
            Log::error('Captura de PayPal con monto o moneda distintos a los del pedido.', [
                'order' => $order->id,
                'esperado' => $order->total,
                'paypal' => $result,
            ]);

            // El dinero entró pero no es lo acordado: no se marca pagado y lo resuelve
            // una persona. La marca además lo saca del cron de abandonos y del aviso de
            // recuperación, que si no le dirían al cliente que no recibimos su pago.
            $this->marcarParaAtencion($order, sprintf(
                'PayPal cobró un monto o moneda distintos de los del pedido (%s %s). Revisar antes de enviar.',
                number_format((float) $order->total, 2),
                config('services.paypal.currency', 'USD')
            ));

            return false;
        }

        // Express: los datos del comprador llegan recién ahora. Se completan dentro de
        // markPaid, con el pedido bloqueado y antes de marcarlo pagado: así la
        // confirmación tiene email, y si el retorno y el webhook llegan juntos los datos
        // se completan una sola vez.
        $this->markPaid($payment, $result, function (Order $bloqueado) use ($result) {
            if ($bloqueado->esperaDatosDePaypal()) {
                $this->completarPedidoConDatosDePaypal($bloqueado, $result);
            }
        });

        return true;
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

        // Sin cuenta en el sitio: el email de PayPal queda como el email verificado del
        // pedido. Se deja constancia de que lo informó PayPal (y no alguien
        // escribiéndolo en un formulario): es lo que habilita vincular otros pedidos y
        // crear la cuenta con ese email. El que escribió el cliente en la tienda sigue
        // en notification_email, intacto: los avisos van a ese.
        if ($email && ! $order->user_id && ! $order->guest_email) {
            $cambios['guest_email'] = $email;
            $cambios['email_verificado_por'] = 'paypal';
        }

        $escrito = $order->notification_email;

        if ($email && $escrito && strcasecmp(trim($email), trim($escrito)) !== 0) {
            $cambios['notes'] = trim((string) $order->notes."\n".sprintf(
                'Email de PayPal (%s) distinto del escrito en la tienda (%s). Los avisos van al escrito.',
                $email,
                $escrito
            ));
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

        // Todas las capturas en la moneda con la que se creó la orden: 304.90 BRL no
        // son 304.90 USD aunque el número coincida.
        $monedas = array_values(array_unique(array_filter(
            (array) data_get($result, 'purchase_units.*.payments.captures.*.amount.currency_code')
        )));
        $monedaOk = $monedas === [] || $monedas === [config('services.paypal.currency', 'USD')];

        return $monedaOk && abs($cobrado - (float) $order->total) < 0.01;
    }

    // ───────────────────────── Stripe ─────────────────────────

    /**
     * Estados en los que Stripe todavía acepta pagar un PaymentIntent existente. Si el
     * intent está en otro (cancelado, procesando, ya cobrado) hay que crear uno nuevo.
     */
    private const STRIPE_INTENT_REUSABLE = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
    ];

    public function stripeIntent(Request $request): JsonResponse
    {
        $order = Order::findOrFail($request->validate(['order_id' => 'required|uuid'])['order_id']);

        // Mismas guardas que paypalCreate: sin esto, conocer un UUID alcanzaba para
        // generar cobros sobre el pedido de otra persona.
        if (! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            abort(403, 'No puedes pagar este pedido.');
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Este pedido ya fue pagado.'], 409);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return response()->json(['message' => 'Este pedido ya no se puede pagar.'], 409);
        }

        // Reutiliza el intent creado hace menos de 30 minutos si Stripe lo sigue
        // aceptando: evita cobrar dos veces al cliente que recarga o vuelve atrás.
        if ($reusado = $this->intentReutilizable($order)) {
            return response()->json($reusado + ['reused' => true]);
        }

        try {
            $intent = $this->stripe->createPaymentIntent($order);
        } catch (RequestException $e) {
            Log::error('Stripe rechazó la creación del PaymentIntent.', [
                'order' => $order->id,
                'status' => $e->response->status(),
                'body' => $e->response->json(),
            ]);

            return response()->json([
                'message' => 'No pudimos iniciar el pago con tarjeta. Intentá de nuevo o contactanos por WhatsApp.',
            ], 502);
        }

        $this->recordPayment($order, 'stripe', $intent['id'], strtoupper((string) config('services.stripe.currency', 'usd')));

        return response()->json([
            'client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['id'],
        ]);
    }

    /**
     * Devuelve el client_secret de un intent pendiente que todavía se pueda pagar, o
     * null si hay que crear uno nuevo.
     *
     * El client_secret se relee de Stripe en vez de guardarse en nuestra base: es una
     * credencial que permite confirmar ese cobro, y no hace falta tenerla en reposo.
     *
     * @return array{client_secret: string, payment_intent_id: string}|null
     */
    private function intentReutilizable(Order $order): ?array
    {
        $pendiente = Payment::where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if (! $pendiente) {
            return null;
        }

        try {
            $intent = $this->stripe->confirmPayment($pendiente->transaction_id);
        } catch (RequestException $e) {
            // El intent ya no existe o Stripe no responde: se crea uno nuevo.
            Log::warning('No se pudo releer un PaymentIntent pendiente de Stripe.', [
                'order' => $order->id,
                'intent' => $pendiente->transaction_id,
                'status' => $e->response->status(),
            ]);

            return null;
        }

        $pagable = in_array($intent['status'] ?? '', self::STRIPE_INTENT_REUSABLE, true);
        // El monto del intent tiene que seguir coincidiendo con el del pedido: si el
        // total cambió, reutilizarlo cobraría el importe viejo.
        $montoCoincide = (int) ($intent['amount'] ?? 0) === $this->stripe->montoEnCentavos($order);

        if (! $pagable || ! $montoCoincide || blank($intent['client_secret'] ?? null)) {
            return null;
        }

        return [
            'client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['id'],
        ];
    }

    public function stripeConfirm(Request $request): JsonResponse
    {
        $data = $request->validate(['payment_intent_id' => 'required|string']);

        $payment = Payment::with('order.address')
            ->where('provider', 'stripe')
            ->where('transaction_id', $data['payment_intent_id'])
            ->first();

        $order = $payment?->order;

        if (! $payment || ! $order) {
            return response()->json(['message' => 'No encontramos ese pago.'], 404);
        }

        // El id del intent no autoriza por sí solo: hace falta el token del pedido o
        // una sesión con permiso, igual que para iniciar el cobro.
        if (! $order->isAccessibleBy($request->user('sanctum'), Order::tokenFromRequest($request))) {
            abort(403, 'No puedes consultar este pago.');
        }

        try {
            $intent = $this->stripe->confirmPayment($data['payment_intent_id']);
        } catch (RequestException $e) {
            Log::error('Stripe rechazó la consulta del PaymentIntent.', [
                'order' => $order->id,
                'intent' => $data['payment_intent_id'],
                'status' => $e->response->status(),
                'body' => $e->response->json(),
            ]);

            return response()->json([
                'message' => 'No pudimos confirmar el pago con tarjeta.',
            ], 502);
        }

        // El estado lo dice Stripe, no el cliente: acá se relee la fuente de verdad.
        if (($intent['status'] ?? null) === 'succeeded') {
            $this->markPaid($payment, $intent);
        }

        // Respuesta acotada (el mismo helper que usa PayPal): antes devolvía el modelo
        // Order completo, y con él el access_token del pedido, las notas y la dirección.
        return $this->estadoDelPago($intent['status'] ?? null, $order->fresh(['address']));
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

        // Captura rechazada: solo se anota el fallo, para adelantar el aviso de
        // recuperación. No se cancela ni se toca el estado de pago.
        if ($event === 'PAYMENT.CAPTURE.DENIED') {
            $reference = $request->input('resource.supplementary_data.related_ids.order_id')
                ?? $request->input('resource.id');
            $order = Payment::with('order')->where('provider', 'paypal')->where('transaction_id', $reference)->first()?->order;

            if ($order) {
                $this->registrarPagoFallido($order, 'paypal', 'PAYMENT.CAPTURE.DENIED');
            }

            return response()->json(['received' => true, 'handled' => (bool) $order]);
        }

        // El comprador aprobó en PayPal. El dinero todavía no se movió, así que esto NO
        // marca pagado: captura la orden desde el servidor, con las mismas validaciones
        // que el retorno, y solo una captura COMPLETED marca el pedido.
        if ($event === 'CHECKOUT.ORDER.APPROVED') {
            return $this->capturarAprobadaDesdeWebhook((string) $request->input('resource.id'));
        }

        // Solo la captura confirma el cobro.
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

    /**
     * Captura una orden que el cliente aprobó en PayPal. Normalmente la captura el
     * retorno al sitio (paypalCapture); pero si el cliente cerró la pestaña después de
     * aprobar, nadie la capturaba y el pedido quedaba sin cobrar ni completar.
     *
     * Seguro frente al retorno simultáneo: PayPal captura una orden una sola vez (el
     * segundo intento recibe ORDER_ALREADY_CAPTURED y la relee) y markPaid procesa el
     * pedido una sola vez, bloqueado. Un 2xx sin capturar no hace que PayPal reintente:
     * el retorno del cliente, si llega, lo vuelve a intentar.
     */
    private function capturarAprobadaDesdeWebhook(string $paypalOrderId): JsonResponse
    {
        $payment = Payment::with('order')
            ->where('provider', 'paypal')
            ->where('transaction_id', $paypalOrderId)
            ->first();

        if (! $payment?->order) {
            Log::warning('Webhook de aprobación de PayPal sin pago asociado.', ['paypal_order' => $paypalOrderId]);

            return response()->json(['received' => true, 'handled' => false]);
        }

        if ($payment->order->payment_status === 'paid') {
            return response()->json(['received' => true, 'handled' => true]);
        }

        try {
            $result = $this->capturarOReleer($paypalOrderId);
        } catch (RequestException $e) {
            $motivo = data_get($e->response->json(), 'details.0.issue', 'CAPTURE_FAILED');

            Log::warning('Webhook de aprobación de PayPal: la captura falló.', [
                'pedido' => $payment->order->order_number,
                'paypal_order' => $paypalOrderId,
                'motivo' => $motivo,
            ]);

            if ($motivo !== 'ORDER_NOT_APPROVED') {
                $this->registrarPagoFallido($payment->order, 'paypal', $motivo);
            }

            return response()->json(['received' => true, 'handled' => false]);
        }

        $aplicado = $this->aplicarCapturaPaypal($payment, $result);

        return response()->json(['received' => true, 'handled' => $aplicado && ($result['status'] ?? null) === 'COMPLETED']);
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

        // Cobro rechazado (fondos insuficientes, tarjeta declinada...): solo se anota el
        // fallo, para adelantar el aviso de recuperación. El cliente puede reintentar con
        // el mismo intent, así que ni se cancela el pedido ni se toca su estado de pago.
        if ($event === 'payment_intent.payment_failed') {
            $intentId = $request->input('data.object.id');
            $order = Payment::with('order')->where('provider', 'stripe')->where('transaction_id', $intentId)->first()?->order;

            if ($order) {
                $this->registrarPagoFallido(
                    $order,
                    'stripe',
                    (string) $request->input('data.object.last_payment_error.code', 'payment_failed')
                );
            } else {
                Log::warning('Webhook Stripe de pago fallido sobre un pago que no existe.', ['intent' => $intentId]);
            }

            return response()->json(['received' => true]);
        }

        // payment_intent.succeeded es el otro evento con efecto. El resto sale por el
        // 'received' de abajo: contestar 2xx evita que Stripe reintente algo que de
        // todos modos no vamos a procesar.
        if ($event !== 'payment_intent.succeeded') {
            return response()->json(['received' => true]);
        }

        $intent = $request->input('data.object', []);
        $intentId = $intent['id'] ?? null;

        // Se filtra por provider igual que stripeConfirm: los ids de las pasarelas no
        // colisionan hoy, pero que los dos caminos busquen distinto es pedir problemas.
        $payment = Payment::with('order')
            ->where('provider', 'stripe')
            ->where('transaction_id', $intentId)
            ->first();

        $order = $payment?->order;

        if (! $payment || ! $order) {
            Log::warning('Webhook Stripe sobre un pago que no existe.', ['intent' => $intentId]);

            return response()->json(['received' => true]);
        }

        // El monto lo fija el backend al crear el intent, así que el cliente no puede
        // alterarlo. Pero si el total del pedido se editó después, cobramos el importe
        // viejo: eso no se marca pagado en silencio.
        $cobrado = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
        $esperado = $this->stripe->montoEnCentavos($order);

        if ($cobrado !== $esperado) {
            Log::error('Webhook Stripe con monto distinto al del pedido: requiere atención manual.', [
                'pedido' => $order->order_number,
                'cobrado_centavos' => $cobrado,
                'esperado_centavos' => $esperado,
                'intent' => $intentId,
            ]);

            $this->marcarParaAtencion($order, sprintf(
                'Stripe cobró %s pero el pedido totaliza %s. Revisar antes de enviar.',
                number_format($cobrado / 100, 2),
                number_format($esperado / 100, 2)
            ));

            // No se marca pagado: el importe no es el acordado y lo decide una persona.
            // El pedido queda fuera del alcance del cron de abandonos por su marca de
            // atención, así que no se va a cancelar solo mientras se revisa.
            return response()->json(['received' => true]);
        }

        $this->markPaid($payment, $intent);

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

    /**
     * Anota que la pasarela rechazó un cobro. Solo sirve para que el aviso de
     * recuperación salga a los pocos minutos del rechazo (ver RecuperarPagosPendientes):
     * no marca nada como pagado ni cancela. Si el pedido ya no está pendiente, no se
     * anota: un rechazo viejo que llega tarde no debe pisar un pago que sí entró.
     */
    private function registrarPagoFallido(Order $order, string $pasarela, string $motivo): void
    {
        if ($order->payment_status !== 'pending') {
            return;
        }

        // Cada rechazo reinicia la espera: si reintenta y vuelve a fallar, se le da el
        // mismo margen desde el último intento.
        $order->forceFill(['payment_failed_at' => now()])->save();

        Log::info('Pago rechazado por la pasarela.', [
            'pedido' => $order->order_number,
            'pasarela' => $pasarela,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Marca el pago aprobado y el pedido pagado, descuenta el stock y avisa al cliente.
     *
     * Todo con el pedido bloqueado: el retorno del cliente y un webhook pueden llegar a
     * la vez, y sin el bloqueo los dos verían "pendiente" y descontarían el stock (y
     * mandarían la confirmación) dos veces. El segundo encuentra el pedido pagado y no
     * hace nada más.
     *
     * @param  (callable(Order): void)|null  $antesDeMarcar  Corre con el pedido bloqueado
     *                                                       y solo si todavía no estaba pagado.
     */
    private function markPaid(Payment $payment, array $gatewayResponse, ?callable $antesDeMarcar = null): void
    {
        $order = DB::transaction(function () use ($payment, $gatewayResponse, $antesDeMarcar) {
            $payment->update([
                'status' => 'approved',
                'gateway_response' => $gatewayResponse,
                'paid_at' => now(),
            ]);

            $order = Order::with('address')->whereKey($payment->order_id)->lockForUpdate()->first();

            // Ya pagado (lo procesó el otro camino): ni stock ni email de nuevo.
            if (! $order || $order->payment_status === 'paid') {
                return null;
            }

            if ($antesDeMarcar) {
                $antesDeMarcar($order);
                $order->refresh();
            }

            // El link de aprobación de PayPal vive más que la ventana del cron de
            // abandonos, así que un pedido ya cancelado puede pagarse después. El pago
            // manda (el dinero entró), pero el admin tiene que saber que revivió algo
            // que había dado por muerto.
            $revivido = $order->status === 'cancelled';

            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
            ]);

            if ($revivido) {
                $this->marcarParaAtencion($order, 'Pedido cancelado por falta de pago y luego pagado. Confirmar que sigue vigente.');
            }

            // El stock se descuenta acá, con el pago confirmado, no al crear el pedido.
            $this->descontarStock($order);

            return $order;
        });

        if (! $order) {
            return;
        }

        // Fuera de la transacción: un SMTP lento no debe tener el pedido bloqueado.
        // Va al email que el cliente escribió en la tienda, si lo hay (ver emailDelCliente).
        $order = $order->fresh(['items.variant.product', 'address', 'user']);
        $email = $order->emailDelCliente();

        if ($email) {
            try {
                Mail::to($email)->send(new OrderConfirmedMail($order));
            } catch (\Throwable $e) {
                Log::error('Error enviando confirmación de pedido', ['order' => $order->id, 'error' => $e->getMessage()]);
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
