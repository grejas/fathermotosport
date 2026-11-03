<?php

namespace App\Services;

use App\Mail\CorreoCumpleanos;
use App\Models\BirthdaySetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Correo de cumpleaños de ejemplo (vista previa y envío de prueba del panel, y el comando
 * cumpleanos:prueba). Con los ajustes actuales y el código BDAY-PRUEBA: no crea cupones
 * ni filas, no pasa por el cupo y su enlace de baja no sirve (el cliente no existe).
 *
 * Sin $nombre el saludo va sin nombre ("¡Feliz cumpleaños!"), como le llega a un cliente
 * sin nombre utilizable; con $nombre se arma igual que en el envío real (PrimerNombre).
 */
class CumpleanosPrueba
{
    public const CODIGO = 'BDAY-PRUEBA';

    public function correo(string $idioma = 'es', ?string $nombre = null): CorreoCumpleanos
    {
        $ajustes = BirthdaySetting::actual();
        $cliente = (new User)->forceFill([
            'id' => (string) Str::uuid(),
            'first_name' => (string) $nombre,
            'last_name' => '',
            'email' => 'prueba@fathermotosport.com',
            'locale' => $idioma,
        ]);

        return new CorreoCumpleanos(
            $cliente,
            self::CODIGO,
            (float) $ajustes->discount_percent,
            CumpleanosService::vencimiento(CumpleanosService::hoy(), $ajustes->valid_days),
            (int) CumpleanosService::hoy()->year,
        );
    }

    public function html(string $idioma = 'es', ?string $nombre = null): string
    {
        return $this->correo($idioma, $nombre)->render();
    }

    public function enviar(string $email, string $idioma = 'es', ?string $nombre = null): void
    {
        Mail::to($email)->send($this->correo($idioma, $nombre));
    }
}
