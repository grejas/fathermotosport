<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Genera la siguiente ocurrencia de las promociones flash recurrentes.
// Cada 10 min basta: generamos la SIGUIENTE ocurrencia con anticipación, no en tiempo real.
Schedule::command('flash-promos:generate-next-occurrences')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Cancela los pedidos que quedaron sin pagar. Importa sobre todo por PayPal Express:
// un clic en el carrito ya crea el pedido, así que los abandonos se acumulan rápido.
// Cada 10 min para que el panel no se llene de pedidos muertos; 30 min de margen es
// tiempo de sobra para completar un pago que ya está empezado.
// Solo apaga el interruptor en el panel: el precio ya deja de tener el descuento al
// pasar la fecha de fin (PrecioFinal lo evalúa en cada consulta).
Schedule::command('categorias:vencer-descuentos')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('orders:cancel-abandoned --apply --minutes=30')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Único aviso a quien no completó el pago ("no pudimos recibir tu pago"). Cada minuto
// porque las ventanas son cortas: 5 min de espera y 30 hasta que el pedido se cancela.
// withoutOverlapping evita dos ejecuciones a la vez si una consulta a la pasarela tarda;
// de todos modos el comando marca el envío con el pedido bloqueado.
Schedule::command('payments:recover-pending')
    ->everyMinute()
    ->withoutOverlapping();

// Correos de cumpleaños, una vez al día a las 10:00 de La Paz. Si el envío automático está
// apagado en el panel, el comando no hace nada. Recupera los 2 días anteriores y no
// duplica (un correo por cliente y por año).
Schedule::command('cumpleanos:enviar')
    ->dailyAt(config('marketing.birthday_send_time', '10:00'))
    ->timezone(config('marketing.timezone', 'America/La_Paz'))
    ->withoutOverlapping();

// Avisos por correo (descuentos de categoría; campañas en la fase 4) por tandas: cada
// minuto toma hasta N pendientes y los envía respetando el cupo de Resend. Sin envíos en
// curso no hace nada.
Schedule::command('campanas:enviar')
    ->everyMinute()
    ->withoutOverlapping();
