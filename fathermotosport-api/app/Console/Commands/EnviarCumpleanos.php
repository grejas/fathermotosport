<?php

namespace App\Console\Commands;

use App\Models\BirthdaySetting;
use App\Services\CumpleanosService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Correos de cumpleaños del día. Programado a las 10:00 de La Paz (routes/console.php),
 * sin solaparse. No hace nada si el envío automático está apagado en el panel.
 */
class EnviarCumpleanos extends Command
{
    protected $signature = 'cumpleanos:enviar {--fecha= : AAAA-MM-DD, para correr como si fuera ese dia (hora de La Paz)}';

    protected $description = 'Envía los correos de cumpleaños del día (y recupera los 2 días anteriores).';

    public function handle(CumpleanosService $cumpleanos): int
    {
        if (! BirthdaySetting::actual()->auto_send) {
            $this->info('El envío automático de cumpleaños está apagado (panel > Cumpleaños). No se envía nada.');

            return self::SUCCESS;
        }

        $hoy = $this->option('fecha')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('fecha'), CumpleanosService::zona())
            : CumpleanosService::hoy();

        $resumen = $cumpleanos->enviar($hoy);

        $this->info(sprintf(
            'Cumpleaños %s: %d enviados, %d pospuestos por cupo, %d fallidos, %d omitidos, %d ya enviados.',
            $hoy->toDateString(),
            $resumen['enviados'],
            $resumen['pospuestos'],
            $resumen['fallidos'],
            $resumen['omitidos'],
            $resumen['ya_enviados'],
        ));

        return self::SUCCESS;
    }
}
