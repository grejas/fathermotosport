<?php

namespace App\Console\Commands;

use App\Services\CampanaManual;
use App\Services\EnvioPorTandas;
use Illuminate\Console\Command;

/**
 * Envía una tanda de los avisos y campañas en curso. Programado cada minuto
 * (routes/console.php), sin solaparse. Antes, pasa a "enviando" las campañas programadas
 * cuya hora llegó (también las atrasadas si el servidor estuvo caído). Sin envíos en
 * curso, no hace nada.
 */
class EnviarCampanas extends Command
{
    protected $signature = 'campanas:enviar';

    protected $description = 'Inicia las campañas programadas y envía la siguiente tanda de los avisos por correo en curso.';

    public function handle(CampanaManual $campanas, EnvioPorTandas $tandas): int
    {
        if ($iniciadas = $campanas->iniciarProgramadas()) {
            $this->info("Campañas programadas iniciadas: {$iniciadas}.");
        }

        $r = $tandas->procesar();

        if (array_sum($r) > 0) {
            $this->info(sprintf(
                'Tanda: %d enviados, %d omitidos, %d fallidos, %d pospuestos por cupo, %d cancelados.',
                $r['enviados'], $r['omitidos'], $r['fallidos'], $r['pospuestos'], $r['cancelados'],
            ));
        }

        return self::SUCCESS;
    }
}
