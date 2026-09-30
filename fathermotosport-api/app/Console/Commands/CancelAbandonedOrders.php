<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cancela los pedidos que quedaron sin pagar.
 *
 * Hace falta sobre todo por PayPal Express: un clic desde el carrito ya crea el
 * pedido, así que los abandonos dejan pedidos pendientes que nunca se van a pagar.
 * El stock no se devuelve porque nunca se descontó: eso pasa al confirmar el pago.
 */
class CancelAbandonedOrders extends Command
{
    protected $signature = 'orders:cancel-abandoned
                            {--minutes=30 : Antigüedad mínima del pedido sin pagar}
                            {--apply : Aplica los cambios (sin este flag solo muestra qué haría)}';

    protected $description = 'Cancela los pedidos que quedaron pendientes de pago.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $minutos = max(1, (int) $this->option('minutes'));
        $limite = now()->subMinutes($minutos);

        $this->info($apply
            ? "Modo APPLY: se cancelarán los pedidos sin pagar anteriores a {$limite}."
            : "Modo DRY-RUN: nada se modifica. Pedidos sin pagar anteriores a {$limite}.");

        $pedidos = Order::with('items')
            ->where('payment_status', 'pending')
            ->where('status', 'pending')
            // Un pedido marcado para revisión no se cancela solo: puede tener dinero
            // cobrado que no cuadra (ver el webhook de Stripe) y lo resuelve una persona.
            ->whereNull('attention_reason')
            ->where('created_at', '<', $limite)
            ->get();

        if ($pedidos->isEmpty()) {
            $this->info('No hay pedidos abandonados.');

            return self::SUCCESS;
        }

        foreach ($pedidos as $order) {
            $detalle = $order->items->map(fn ($item) => $item->quantity.'× '.($item->size ?? 'sin talla'))->implode(', ');
            $this->line("  {$order->order_number} · {$order->created_at} · {$detalle}");

            // El stock no se toca: desde que se descuenta al confirmar el pago
            // (PaymentController::markPaid), un pedido sin pagar nunca lo reservó.
            if ($apply) {
                $order->update(['status' => 'cancelled']);
            }
        }

        $this->newLine();
        $this->info(($apply ? 'Cancelados: ' : 'Se cancelarían: ').$pedidos->count().' pedido(s)');

        if ($apply) {
            Log::info('Pedidos abandonados cancelados.', [
                'pedidos' => $pedidos->pluck('order_number')->all(),
            ]);
        } else {
            $this->comment('Para aplicarlo: php artisan orders:cancel-abandoned --apply');
        }

        return self::SUCCESS;
    }
}
