<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un destinatario de un envío por tandas (clave única campaña + usuario). Las columnas
 * bounced_at y complained_at siguen en la tabla, pero ya nada las llena ni las muestra.
 */
class MailCampaignRecipient extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ENVIANDO = 'enviando';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public const OMITIDO = 'omitido';

    public const CANCELADO = 'cancelado';

    /** Intentos fallidos tras los que queda como fallido. */
    public const MAX_INTENTOS = 3;

    protected $fillable = ['mail_campaign_id', 'user_id', 'email', 'locale', 'status', 'attempts', 'reason', 'claim_token', 'sent_at'];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    /**
     * Motivos guardados (de User::motivoSinPromociones y EnvioPromocional) en lenguaje claro.
     * "rebote" y "queja de spam" solo salen si alguien marca a mano users.email_bounced_at o
     * email_complained_at: se muestran como una exclusión genérica.
     */
    private const MOTIVOS = [
        'dado de baja' => 'Se dio de baja',
        'rebote' => 'Excluido de promociones',
        'queja de spam' => 'Excluido de promociones',
        'correo sin evidencia de verificación' => 'Sin evidencia de verificación',
        'correo no verificado' => 'Correo no verificado',
        'correo con formato inválido' => 'Correo con formato inválido',
        'cuenta inactiva' => 'Cuenta inactiva',
        'cuenta borrada' => 'Cuenta borrada',
        'no es cliente' => 'No es cliente',
        'error al enviar' => 'Error al enviar',
    ];

    /**
     * Motivo para el panel, SOLO en omitidos y fallidos ("Se dio de baja", "Error al
     * enviar"...). En los demás estados, null: un enviado no muestra motivo.
     */
    public function motivoLegible(): ?string
    {
        if (! in_array($this->status, [self::OMITIDO, self::FALLIDO], true) || blank($this->reason)) {
            return null;
        }

        $motivo = trim((string) preg_replace('/^(omitido|cancelado):\s*/u', '', (string) $this->reason));

        return self::MOTIVOS[$motivo] ?? mb_strtoupper(mb_substr($motivo, 0, 1)).mb_substr($motivo, 1);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'mail_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
