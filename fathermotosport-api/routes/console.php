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
