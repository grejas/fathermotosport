<?php

namespace App\Mail;

use App\Models\User;
use App\Support\BajaDePromociones;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Base de TODO correo promocional (cumpleaños, avisos de descuento, campañas): idioma del
 * cliente, enlace de baja firmado en el pie y las cabeceras List-Unsubscribe y
 * List-Unsubscribe-Post de un clic (RFC 8058) que piden Gmail y Yahoo.
 *
 * $referencia identifica el envío (ej. "campaign:12", "birthday:2027"): va a la baja y
 * como clave de idempotencia de Resend, para que un reintento no duplique el correo.
 * Se envía solo con App\Services\EnvioPromocional, que antes comprueba que el cliente
 * pueda recibirlo. Los transaccionales NO extienden esta clase.
 */
abstract class CorreoPromocional extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $destinatario, public string $referencia)
    {
        $this->locale($this->idioma());
    }

    public function idioma(): string
    {
        return in_array($this->destinatario->locale, ['es', 'pt', 'en'], true) ? $this->destinatario->locale : 'es';
    }

    /** Variables comunes para la plantilla emails.layouts.promocional. */
    protected function datosDePlantilla(): array
    {
        return [
            'idiomaCorreo' => $this->idioma(),
            'urlBaja' => BajaDePromociones::urlPagina($this->destinatario),
        ];
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.BajaDePromociones::urlUnClic($this->destinatario).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            // Resend no repite el envío si llega otra vez la misma clave (24 horas).
            'Resend-Idempotency-Key' => mb_substr($this->referencia.':'.$this->destinatario->id, 0, 256),
        ]);
    }
}
