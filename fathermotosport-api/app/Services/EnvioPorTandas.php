<?php

namespace App\Services;

use App\Mail\CorreoAvisoDescuento;
use App\Mail\CorreoCampana;
use App\Mail\CorreoPromocional;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use Illuminate\Support\Str;

/**
 * Envía por tandas los destinatarios pendientes de los envíos en curso (avisos de
 * descuento y campañas manuales). Lo corre campanas:enviar cada minuto.
 *
 * - Toma hasta N pendientes con una actualización atómica (claim_token): dos procesos no
 *   toman el mismo destinatario.
 * - Envía por EnvioPromocional: omite sin llamar a Resend a quien ya no puede recibir
 *   (baja, sin evidencia...) y respeta el límite por minuto y el tope diario. Si se agota,
 *   lo que falta queda pendiente para la próxima pasada (mañana, si es el tope diario).
 * - Un fallo se reintenta hasta 3 veces; no detiene al resto.
 * - Antes de cada tanda y de cada correo se comprueba que el envío siga "enviando" (no
 *   pausado ni cancelado desde el panel) y que siga valiendo (el descuento vigente y sin
 *   cambios); si no, se cancela lo que falta y queda registrado. Al pausar, lo tomado por
 *   la tanda vuelve a pendiente.
 * - Si el proceso cae justo después de enviar, el destinatario queda "enviando"; pasados 10
 *   minutos vuelve a pendiente, y la clave de idempotencia de Resend evita el duplicado.
 */
class EnvioPorTandas
{
    /** Minutos tras los que un "enviando" sin terminar se considera de un proceso caído. */
    public const MINUTOS_ATASCADO = 10;

    public function __construct(private EnvioPromocional $envio, private AvisoDescuento $avisos) {}

    /** @return array<string, int> enviados, omitidos, fallidos, pospuestos, cancelados */
    public function procesar(): array
    {
        $resumen = ['enviados' => 0, 'omitidos' => 0, 'fallidos' => 0, 'pospuestos' => 0, 'cancelados' => 0];

        MailCampaignRecipient::where('status', MailCampaignRecipient::ENVIANDO)
            ->where('updated_at', '<', now()->subMinutes(self::MINUTOS_ATASCADO))
            ->update(['status' => MailCampaignRecipient::PENDIENTE, 'claim_token' => null]);

        $cupoAgotado = false;
        foreach (MailCampaign::where('status', MailCampaign::ENVIANDO)->orderBy('id')->get() as $campana) {
            if ($cupoAgotado) {
                break;
            }

            if ($motivo = $this->motivoDeCancelacion($campana)) {
                $resumen['cancelados'] += $campana->cancelar($motivo);

                continue;
            }

            $tanda = $this->tomarTanda($campana);
            foreach ($tanda as $i => $destinatario) {
                if ($campana->estadoActual() !== MailCampaign::ENVIANDO) {
                    // Pausada o cancelada desde el panel durante la tanda.
                    $this->liberar($tanda->slice($i)->pluck('id')->all());
                    break;
                }

                if ($motivo = $this->motivoDeCancelacion($campana)) {
                    $resumen['cancelados'] += $campana->cancelar($motivo);
                    break;
                }

                $estado = $this->enviarA($campana, $destinatario);
                $resumen[match ($estado) {
                    'enviado' => 'enviados',
                    'omitido' => 'omitidos',
                    'pospuesto' => 'pospuestos',
                    default => 'fallidos',
                }]++;

                if ($estado === 'pospuesto') {
                    // Cupo agotado: lo que queda de la tanda vuelve a pendiente.
                    $this->liberar($tanda->slice($i + 1)->pluck('id')->all());
                    $cupoAgotado = true;
                    break;
                }
            }

            $this->cerrarSiTermino($campana);
        }

        return $resumen;
    }

    private function motivoDeCancelacion(MailCampaign $campana): ?string
    {
        return match ($campana->type) {
            MailCampaign::TIPO_DESCUENTO => $this->avisos->motivoDeCancelacion($campana),
            default => null,
        };
    }

    /** Hasta N pendientes, marcados con una actualización atómica. */
    private function tomarTanda(MailCampaign $campana)
    {
        $ids = $campana->recipients()->where('status', MailCampaignRecipient::PENDIENTE)
            ->orderBy('id')->limit(max(1, (int) config('marketing.limits.per_minute', 60)))->pluck('id');
        $token = (string) Str::uuid();

        MailCampaignRecipient::whereIn('id', $ids)->where('status', MailCampaignRecipient::PENDIENTE)
            ->update(['status' => MailCampaignRecipient::ENVIANDO, 'claim_token' => $token, 'updated_at' => now()]);

        return MailCampaignRecipient::where('claim_token', $token)->where('status', MailCampaignRecipient::ENVIANDO)->orderBy('id')->get();
    }

    private function liberar(array $ids): void
    {
        if ($ids !== []) {
            MailCampaignRecipient::whereIn('id', $ids)->where('status', MailCampaignRecipient::ENVIANDO)
                ->update(['status' => MailCampaignRecipient::PENDIENTE, 'claim_token' => null]);
        }
    }

    private function correoPara(MailCampaign $campana, $user): CorreoPromocional
    {
        return match ($campana->type) {
            MailCampaign::TIPO_DESCUENTO => new CorreoAvisoDescuento($user, $campana->payload, "campaign:{$campana->id}"),
            MailCampaign::TIPO_MANUAL => new CorreoCampana($user, $campana->payload ?? [], "campaign:{$campana->id}"),
        };
    }

    private function enviarA(MailCampaign $campana, MailCampaignRecipient $destinatario): string
    {
        $user = $destinatario->user;
        if (! $user) {
            $destinatario->update(['status' => MailCampaignRecipient::OMITIDO, 'reason' => 'omitido: cuenta borrada', 'claim_token' => null]);

            return 'omitido';
        }

        $resultado = $this->envio->enviar($this->correoPara($campana, $user));

        match ($resultado['estado']) {
            'enviado' => $destinatario->update(['status' => MailCampaignRecipient::ENVIADO, 'sent_at' => now(), 'reason' => null, 'claim_token' => null]),
            'omitido' => $destinatario->update(['status' => MailCampaignRecipient::OMITIDO, 'reason' => $resultado['motivo'], 'claim_token' => null]),
            'pospuesto' => $destinatario->update(['status' => MailCampaignRecipient::PENDIENTE, 'reason' => $resultado['motivo'], 'claim_token' => null]),
            default => $destinatario->update([
                'attempts' => $destinatario->attempts + 1,
                'status' => $destinatario->attempts + 1 >= MailCampaignRecipient::MAX_INTENTOS ? MailCampaignRecipient::FALLIDO : MailCampaignRecipient::PENDIENTE,
                'reason' => $resultado['motivo'],
                'claim_token' => null,
            ]),
        };

        return $resultado['estado'];
    }

    private function cerrarSiTermino(MailCampaign $campana): void
    {
        $campana->refresh();
        if ($campana->status !== MailCampaign::ENVIANDO) {
            return;
        }

        $campana->recontar();
        if ($campana->pending_count === 0) {
            $campana->update(['status' => MailCampaign::COMPLETADA, 'finished_at' => now()]);
        }
    }
}
