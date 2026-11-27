<?php

namespace App\Mail;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Acuse de recibo del desistimiento: lo que el cliente declaró, con fecha y hora.
 * Automático (sale de MAIL_FROM); las respuestas van a la casilla de ventas.
 * Copia oculta a ventas para que el equipo lo gestione.
 */
class WithdrawalReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WithdrawalRequest $solicitud) {}

    public function envelope(): Envelope
    {
        $ventas = new Address(config('mail.ventas.address'), config('mail.ventas.name'));

        return new Envelope(
            replyTo: [$ventas],
            subject: __('desistimiento.subject', [
                'order' => $this->solicitud->order_number,
            ], $this->idioma()),
        );
    }

    public function content(): Content
    {
        $l = $this->idioma();
        $s = $this->solicitud;

        return new Content(
            view: 'emails.desistimiento',
            with: [
                'emailLocale' => $l,
                't' => fn (string $key, array $rep = []) => __('desistimiento.'.$key, $rep, $l),
                'referencia' => $s->referencia(),
                'fecha' => $s->created_at->copy()->setTimezone('UTC')->format('d/m/Y H:i').' UTC',
                'pedido' => $s->order_number,
                'nombre' => $s->name,
                'email' => $s->email,
                'items' => $s->items,
                'motivo' => $s->reason,
                'url' => rtrim((string) config('app.frontend_url'), '/').($l === 'es' ? '' : '/'.$l).'/returns-policy',
            ],
        );
    }

    private function idioma(): string
    {
        return in_array($this->solicitud->locale, ['es', 'pt', 'en'], true) ? $this->solicitud->locale : 'es';
    }
}
