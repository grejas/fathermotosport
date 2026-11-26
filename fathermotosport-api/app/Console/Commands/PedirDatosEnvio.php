<?php

namespace App\Console\Commands;

use App\Mail\ConfirmarDatosEnvioMail;
use App\Models\Order;
use App\Services\EmailService;
use Illuminate\Console\Command;

/**
 * Le escribe al cliente desde la casilla de ventas para confirmar visor, talla y
 * dirección antes de despachar. Lo mismo que el botón "Pedir datos de envío" del panel.
 *
 *   php artisan pedidos:pedir-datos FMS-0065 --ver   (solo muestra el correo)
 *   php artisan pedidos:pedir-datos FMS-0065         (lo envía)
 */
class PedirDatosEnvio extends Command
{
    protected $signature = 'pedidos:pedir-datos {pedido* : Números de pedido (FMS-0065 …)} {--ver : Solo muestra el correo, no lo envía} {--nota= : Línea opcional al inicio del correo}';

    protected $description = 'Envía desde ventas@ el correo para confirmar visor, talla y dirección de envío.';

    public function handle(EmailService $emails): int
    {
        $fallos = 0;

        foreach ($this->argument('pedido') as $numero) {
            $order = Order::where('order_number', $numero)->first();
            if (! $order) {
                $this->error("No existe el pedido {$numero}.");
                $fallos++;

                continue;
            }

            $mail = new ConfirmarDatosEnvioMail($order, $this->option('nota'));
            $destino = $order->emailDelCliente() ?? '(sin email)';

            if ($this->option('ver')) {
                $this->line('De: '.config('mail.ventas.name').' <'.config('mail.ventas.address').">  Para: {$destino}");
                $this->line('Asunto: '.$mail->envelope()->subject);
                $this->line($mail->render());

                continue;
            }

            if ($emails->sendConfirmarDatosEnvio($order, $this->option('nota'))) {
                $this->info("{$numero}: enviado a {$destino} desde ".config('mail.ventas.address'));
            } else {
                $this->error("{$numero}: no se pudo enviar (ver log).");
                $fallos++;
            }
        }

        return $fallos ? self::FAILURE : self::SUCCESS;
    }
}
