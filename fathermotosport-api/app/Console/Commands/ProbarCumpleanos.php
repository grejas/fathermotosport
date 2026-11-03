<?php

namespace App\Console\Commands;

use App\Services\CumpleanosPrueba;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda el correo de cumpleaños a UN correo de prueba, con los ajustes actuales y un
 * código de ejemplo (BDAY-PRUEBA). No crea cupones ni filas, y no cuenta en el cupo.
 */
class ProbarCumpleanos extends Command
{
    protected $signature = 'cumpleanos:prueba {email : Correo que recibe la prueba} {--idioma=es : es, pt o en} {--nombre= : Nombre para el saludo (ej. "gerson rejas" saluda "Gerson"); sin él, saludo sin nombre}';

    protected $description = 'Envía el correo de cumpleaños de ejemplo a un correo de prueba.';

    public function handle(CumpleanosPrueba $prueba): int
    {
        $email = (string) $this->argument('email');
        $idioma = (string) $this->option('idioma');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! in_array($idioma, ['es', 'pt', 'en'], true)) {
            $this->error('Usa un correo válido y --idioma=es, pt o en.');

            return self::INVALID;
        }

        $correo = $prueba->correo($idioma, $this->option('nombre'));
        Mail::to($email)->send($correo);
        $this->info("Correo de prueba ({$idioma}) enviado a {$email}. Saludo: ".($correo->nombre() ?? 'sin nombre').'.');

        return self::SUCCESS;
    }
}
