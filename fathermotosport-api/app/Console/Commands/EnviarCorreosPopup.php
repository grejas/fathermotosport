<?php

namespace App\Console\Commands;

use App\Services\CorreosDelPopup;
use Illuminate\Console\Command;

/** Bienvenida con cupón y recordatorio antes de vencer, para los contactos del popup. */
class EnviarCorreosPopup extends Command
{
    protected $signature = 'popup:correos {--limite=30}';

    protected $description = 'Envía la bienvenida con cupón y el recordatorio de vencimiento a los contactos del popup.';

    public function handle(CorreosDelPopup $correos): int
    {
        $r = $correos->procesar((int) $this->option('limite'));

        if (array_sum($r) > 0) {
            $this->info("Bienvenidas: {$r['bienvenidas']}, recordatorios: {$r['recordatorios']}, fallidos: {$r['fallidos']}.");
        }

        return self::SUCCESS;
    }
}
