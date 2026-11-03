<?php

namespace App\Services;

use App\Mail\CorreoPromocional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Único camino para enviar un correo promocional:
 *
 * 1. Si el cliente no puede recibirlo (User::motivoSinPromociones: correo no verificado,
 *    baja, cuenta inactiva...) se OMITE sin llamar a Resend: no gasta cupo y el motivo
 *    queda para el historial ("omitido: correo no verificado").
 * 2. Si ya se gastó el cupo del minuto o del día (config marketing.limits; el plan
 *    gratuito de Resend da 100 al día), se POSPONE: no se envía ni se cuenta.
 * 3. Si no, se envía y recién entonces se cuenta en el cupo.
 *
 * Nunca lanza: un fallo de un destinatario no detiene al resto.
 */
class EnvioPromocional
{
    /** @return array{estado: 'enviado'|'omitido'|'pospuesto'|'fallido', motivo: ?string} */
    public function enviar(CorreoPromocional $correo): array
    {
        $user = $correo->destinatario;

        if ($motivo = $user->motivoSinPromociones()) {
            return ['estado' => 'omitido', 'motivo' => "omitido: {$motivo}"];
        }

        if (! $this->quedaCupo()) {
            return ['estado' => 'pospuesto', 'motivo' => 'cupo de envío agotado'];
        }

        try {
            Mail::to($user->email)->send($correo);
        } catch (Throwable $e) {
            // Sin el mensaje de la excepción: puede llevar datos del proveedor.
            Log::error('No se pudo enviar un correo promocional.', ['referencia' => $correo->referencia, 'user_id' => $user->id, 'error' => $e::class]);

            return ['estado' => 'fallido', 'motivo' => 'error al enviar'];
        }

        $this->contar();

        return ['estado' => 'enviado', 'motivo' => null];
    }

    /** Correos promocionales enviados hoy (día de La Paz). */
    public function enviadosHoy(): int
    {
        return (int) Cache::get($this->claveDia(), 0);
    }

    private function quedaCupo(): bool
    {
        return (int) Cache::get($this->claveMinuto(), 0) < (int) config('marketing.limits.per_minute', 60)
            && $this->enviadosHoy() < (int) config('marketing.limits.per_day', 100);
    }

    private function contar(): void
    {
        foreach ([$this->claveMinuto() => 120, $this->claveDia() => 172800] as $clave => $segundos) {
            Cache::add($clave, 0, $segundos);
            Cache::increment($clave);
        }
    }

    private function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(config('marketing.timezone', 'America/La_Paz'));
    }

    private function claveMinuto(): string
    {
        return 'promos:enviados:'.$this->ahora()->format('Y-m-d-H-i');
    }

    private function claveDia(): string
    {
        return 'promos:enviados:'.$this->ahora()->format('Y-m-d');
    }
}
