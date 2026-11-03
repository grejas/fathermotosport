<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Promoción de asunto, título, texto y botón, en el idioma del cliente. Es el formato
 * que usarán los avisos de descuento y las campañas (fases 3 y 4); en la fase 1 sirve
 * para probar el envío, la baja y el cupo.
 *
 * @param  array<string, array{asunto: string, titulo: string, texto: string, boton?: string}>  $textos  por idioma
 */
class PromocionSimple extends CorreoPromocional
{
    public function __construct(User $destinatario, string $referencia, public array $textos, public ?string $url = null)
    {
        parent::__construct($destinatario, $referencia);
    }

    /** @return array{asunto: string, titulo: string, texto: string, boton?: string} */
    private function texto(): array
    {
        return $this->textos[$this->idioma()] ?? $this->textos['es'] ?? reset($this->textos);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texto()['asunto']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.promocion-simple',
            with: [...$this->datosDePlantilla(), 'contenido' => $this->texto(), 'url' => $this->url],
        );
    }
}
