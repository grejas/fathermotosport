<?php

namespace App\Models;

use App\Mail\CorreoAvisoDescuento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Un envío de correo promocional por tandas: el aviso de un descuento de categoría
 * (fase 3) o una campaña manual (fase 4). Los destinatarios están en
 * mail_campaign_recipients y los envía el comando campanas:enviar.
 *
 * Estados: borrador y programada (solo campañas manuales), enviando, pausada,
 * completada ("enviada" en el panel) y cancelada.
 */
class MailCampaign extends Model
{
    public const TIPO_DESCUENTO = 'category_discount';

    public const TIPO_MANUAL = 'manual';

    public const BORRADOR = 'borrador';

    public const PROGRAMADA = 'programada';

    public const ENVIANDO = 'enviando';

    public const PAUSADA = 'pausada';

    public const COMPLETADA = 'completada';

    public const CANCELADA = 'cancelada';

    /** Etiquetas del panel. */
    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::PROGRAMADA => 'Programada',
        self::ENVIANDO => 'Enviando',
        self::PAUSADA => 'Pausada',
        self::COMPLETADA => 'Enviada',
        self::CANCELADA => 'Cancelada',
    ];

    protected $fillable = [
        'type', 'name', 'dedupe_key', 'category_id', 'payload', 'status', 'scheduled_at', 'cancel_reason', 'created_by',
        'total_count', 'sent_count', 'pending_count', 'failed_count', 'skipped_count', 'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'pending_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'finished_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MailCampaignRecipient::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Recalcula los contadores desde las filas de destinatarios. */
    public function recontar(): void
    {
        $porEstado = $this->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $this->update([
            'total_count' => (int) $porEstado->sum(),
            'sent_count' => (int) ($porEstado[MailCampaignRecipient::ENVIADO] ?? 0),
            'pending_count' => (int) (($porEstado[MailCampaignRecipient::PENDIENTE] ?? 0) + ($porEstado[MailCampaignRecipient::ENVIANDO] ?? 0)),
            'failed_count' => (int) ($porEstado[MailCampaignRecipient::FALLIDO] ?? 0),
            'skipped_count' => (int) (($porEstado[MailCampaignRecipient::OMITIDO] ?? 0) + ($porEstado[MailCampaignRecipient::CANCELADO] ?? 0)),
        ]);
    }

    /**
     * Agrega los destinatarios como pendientes (en tandas de 500), con el correo y el
     * idioma del momento. Lo usan el aviso de descuento y las campañas manuales.
     *
     * @param  Collection<int, User>  $destinatarios
     */
    public function agregarDestinatarios(Collection $destinatarios): void
    {
        $ahora = now();
        foreach ($destinatarios->chunk(500) as $tanda) {
            MailCampaignRecipient::insertOrIgnore($tanda->map(fn (User $u) => [
                'mail_campaign_id' => $this->id,
                'user_id' => $u->id,
                'email' => $u->email,
                'locale' => $u->locale,
                'status' => MailCampaignRecipient::PENDIENTE,
                'attempts' => 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->values()->all());
        }
    }

    /**
     * Cancela lo que falta (pendientes y los tomados por una tanda en curso) y deja el
     * motivo. Lo enviado queda como está. Devuelve cuántos destinatarios se cancelaron.
     */
    public function cancelar(string $motivo): int
    {
        $cancelados = $this->recipients()
            ->whereIn('status', [MailCampaignRecipient::PENDIENTE, MailCampaignRecipient::ENVIANDO])
            ->update(['status' => MailCampaignRecipient::CANCELADO, 'reason' => "cancelado: {$motivo}", 'claim_token' => null]);

        $this->update(['status' => self::CANCELADA, 'cancel_reason' => $motivo, 'finished_at' => now()]);
        $this->recontar();

        return $cancelados;
    }

    /** Estado actual en la base (otro proceso o el panel pueden haberlo cambiado). */
    public function estadoActual(): ?string
    {
        return self::whereKey($this->getKey())->value('status');
    }

    /** Destinatarios que se dieron de baja después de recibir este correo. */
    public function bajas(): int
    {
        return $this->recipients()
            ->where('mail_campaign_recipients.status', MailCampaignRecipient::ENVIADO)
            ->join('users', 'users.id', '=', 'mail_campaign_recipients.user_id')
            ->whereNotNull('users.promo_unsubscribed_at')
            ->whereColumn('users.promo_unsubscribed_at', '>=', 'mail_campaign_recipients.sent_at')
            ->count();
    }

    /**
     * Fecha de envío para el panel: la programada si todavía no sale; si ya salió, el
     * primer envío; si no, la programada o null (borrador sin fecha).
     */
    public function fechaDeEnvio(): ?CarbonInterface
    {
        if ($this->status === self::PROGRAMADA) {
            return $this->scheduled_at;
        }

        $primero = $this->recipients()->min('sent_at');

        return $primero ? Carbon::parse($primero) : ($this->scheduled_at ?? ($this->type === self::TIPO_DESCUENTO ? $this->created_at : null));
    }

    /** "18 de 20 enviados" (sin destinatarios todavía, null). */
    public function progreso(): ?string
    {
        return $this->total_count > 0 ? "{$this->sent_count} de {$this->total_count} enviados" : null;
    }

    /** Omitidos y bajas, solo los mayores que 0 ("2 omitidos · 1 baja"). */
    public function detallesSecundarios(): ?string
    {
        $partes = array_filter([
            'omitido' => $this->skipped_count,
            'baja' => $this->bajas(),
        ]);

        return $partes === [] ? null : collect($partes)->map(fn (int $n, string $s) => $n.' '.($n === 1 ? $s : "{$s}s"))->implode(' · ');
    }

    /** Asunto en español, como sale en el correo. */
    public function asunto(): ?string
    {
        if ($this->type === self::TIPO_DESCUENTO) {
            $ejemplo = (new User)->forceFill(['id' => (string) Str::uuid(), 'locale' => 'es', 'email' => 'ejemplo@fathermotosport.com']);

            return filled($this->payload) ? (new CorreoAvisoDescuento($ejemplo, $this->payload, 'panel'))->envelope()->subject : null;
        }

        return $this->payload['contenido']['es']['asunto'] ?? null;
    }

    /** Quién la creó, para el panel; los avisos sin autor los registra el sistema. */
    public function creador(): string
    {
        $autor = $this->created_by ? User::withTrashed()->find($this->created_by) : null;

        return $autor ? ($autor->full_name ?: $autor->email) : 'Sistema';
    }

    public function esManual(): bool
    {
        return $this->type === self::TIPO_MANUAL;
    }

    /** Solo un borrador se edita; enviada, en curso o programada no (se duplica). */
    public function editable(): bool
    {
        return $this->status === self::BORRADOR;
    }
}
