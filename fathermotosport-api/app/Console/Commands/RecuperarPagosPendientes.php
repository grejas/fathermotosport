<?php

namespace App\Console\Commands;

use App\Mail\PaymentRecoveryMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaypalService;
use App\Services\Payments\StripeService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Un único correo al cliente que llegó al checkout y no completó el pago, recordándole
 * que puede pagar con PayPal o con tarjeta.
 *
 * Dos casos:
 *   - Abandono silencioso: el pedido se creó hace entre 5 y 30 minutos y no se pagó.
 *     A los 30 lo cancela orders:cancel-abandoned, así que después ya no tiene sentido.
 *   - Pago rechazado: la pasarela avisó del rechazo (payment_failed_at). Sale a los 5
 *     minutos del rechazo, por si el cliente reintenta enseguida.
 *
 * Nunca a un pedido pagado, cancelado, marcado para revisión, de venta cruzada o ya
 * avisado. Antes de enviar se pregunta a la pasarela si el pago no está en curso, y la
 * marca de enviado se pone con el pedido bloqueado: no puede salir dos veces.
 */
class RecuperarPagosPendientes extends Command
{
    protected $signature = 'payments:recover-pending';

    protected $description = 'Envía un único aviso a quien no completó el pago de su pedido.';

    /** Minutos de espera desde la creación del pedido o desde el rechazo del pago. */
    public const ESPERA_MINUTOS = 5;

    /** Hasta cuándo se avisa un abandono: a los 30 minutos el pedido se cancela. */
    public const VENTANA_ABANDONO_MINUTOS = 30;

    /**
     * Estados de un PaymentIntent en los que el cobro sigue su curso (3-D Secure,
     * procesando) o ya entró y el webhook todavía no llegó: no es momento de avisar.
     */
    private const STRIPE_EN_CURSO = ['processing', 'requires_action', 'requires_capture', 'succeeded'];

    /** Orden de PayPal aprobada por el cliente o ya cobrada. */
    private const PAYPAL_EN_CURSO = ['APPROVED', 'COMPLETED'];

    private StripeService $stripe;

    private PaypalService $paypal;

    public function handle(StripeService $stripe, PaypalService $paypal): int
    {
        $this->stripe = $stripe;
        $this->paypal = $paypal;
        $enviados = 0;

        foreach ($this->candidatos() as $order) {
            if (! $order->emailDelCliente()) {
                // Ej. PayPal Express sin terminar: el email llega recién con la captura.
                continue;
            }

            try {
                if ($this->pagoEnCurso($order)) {
                    $this->line("  {$order->order_number}: pago en curso en la pasarela, se reevalúa en la próxima ejecución.");

                    continue;
                }
            } catch (Throwable $e) {
                // Sin saber el estado real, no se avisa: mejor un aviso menos que decirle
                // "no recibimos tu pago" a quien acaba de pagar.
                Log::error('Recuperación de pago: no se pudo consultar la pasarela.', [
                    'pedido' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  {$order->order_number}: falló la consulta a la pasarela, no se envía.");

                continue;
            }

            if ($this->enviar($order)) {
                $enviados++;
                $this->info("  {$order->order_number}: aviso enviado.");
            }
        }

        $this->info("Avisos de pago enviados: {$enviados}");

        return self::SUCCESS;
    }

    /** @return Collection<int, Order> */
    private function candidatos(): Collection
    {
        $limite = now()->subMinutes(self::ESPERA_MINUTOS);
        $finDelAbandono = now()->subMinutes(self::VENTANA_ABANDONO_MINUTOS);

        return $this->pendientesDeAviso(Order::query())
            ->with(['user', 'payments'])
            ->where(fn ($q) => $q
                // Abandono: sin rechazo registrado, entre 5 y 30 minutos de creado.
                ->where(fn ($q) => $q
                    ->whereNull('payment_failed_at')
                    ->where('created_at', '<=', $limite)
                    ->where('created_at', '>', $finDelAbandono))
                // Rechazo: 5 minutos desde el último rechazo.
                ->orWhere('payment_failed_at', '<=', $limite))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Las condiciones que tiene que cumplir el pedido para recibir el aviso. Se usan
     * para elegir los candidatos y de nuevo justo antes de enviar, con el pedido
     * bloqueado: entre una cosa y otra el cliente pudo haber pagado.
     */
    private function pendientesDeAviso(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'pending')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereNull('attention_reason')
            ->whereNull('recovery_email_sent_at')
            ->whereNull('upsell_of_order_id');
    }

    /**
     * ¿El pago está en curso o ya entró según la pasarela? Un pedido sin intent ni orden
     * de PayPal es un abandono y no se consulta nada. Si una consulta falla, lanza.
     */
    private function pagoEnCurso(Order $order): bool
    {
        foreach ($order->payments->where('status', 'pending') as $payment) {
            /** @var Payment $payment */
            $enCurso = match ($payment->provider) {
                'stripe' => in_array(
                    $this->stripe->confirmPayment($payment->transaction_id)['status'] ?? null,
                    self::STRIPE_EN_CURSO,
                    true
                ),
                'paypal' => in_array(
                    $this->paypal->getOrder($payment->transaction_id)['status'] ?? null,
                    self::PAYPAL_EN_CURSO,
                    true
                ),
                default => false,
            };

            if ($enCurso) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marca y envía con el pedido bloqueado, en la misma transacción: si el envío falla
     * la marca se deshace (y se reintenta en la próxima ejecución); si otra ejecución
     * llega a la vez, espera el bloqueo y encuentra la marca puesta.
     */
    private function enviar(Order $candidato): bool
    {
        try {
            return DB::transaction(function () use ($candidato) {
                $order = $this->pendientesDeAviso(Order::query())
                    ->with(['user', 'items.variant.product', 'address'])
                    ->whereKey($candidato->getKey())
                    ->lockForUpdate()
                    ->first();

                $email = $order?->emailDelCliente();

                if (! $order || ! $email) {
                    return false;
                }

                $order->forceFill(['recovery_email_sent_at' => now()])->save();

                Mail::to($email)->send(new PaymentRecoveryMail($order));

                return true;
            });
        } catch (Throwable $e) {
            Log::error('Recuperación de pago: falló el envío del aviso.', [
                'pedido' => $candidato->order_number,
                'error' => $e->getMessage(),
            ]);
            $this->warn("  {$candidato->order_number}: falló el envío, se reintenta en la próxima ejecución.");

            return false;
        }
    }
}
