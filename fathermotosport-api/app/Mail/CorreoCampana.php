<?php

namespace App\Mail;

use App\Models\User;
use App\Services\CampanaManual;
use App\Support\BajaDePromociones;
use App\Support\HtmlDeCorreo;
use App\Support\ImagenDeCampana;
use App\Support\PieDeCorreo;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de una campaña manual (fase 4). Hoy se envía siempre en español, con el pie y
 * la baja en español, sea cual sea el idioma del cliente (CampanaManual::IDIOMAS_ACTIVOS).
 * El cuerpo se vuelve a limpiar al armarlo (HtmlDeCorreo) y
 * los enlaces del botón solo pueden ser https. Plantilla propia en tablas
 * (emails.campana) con la baja visible; de CorreoPromocional vienen List-Unsubscribe,
 * List-Unsubscribe-Post y la clave de idempotencia de Resend.
 */
class CorreoCampana extends CorreoPromocional
{
    public function __construct(User $destinatario, public array $campana, string $referencia)
    {
        parent::__construct($destinatario, $referencia);
    }

    /**
     * Idioma del correo entero (contenido, pie y enlace de baja): el del cliente si es un
     * idioma activo de las campañas; hoy siempre español (CampanaManual::IDIOMAS_ACTIVOS).
     */
    public function idioma(): string
    {
        return CampanaManual::idiomaDeEnvio(parent::idioma());
    }

    /** Contenido que recibe este cliente (hoy, el español). */
    public function contenido(): array
    {
        return CampanaManual::contenidoPara($this->campana, $this->idioma());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) ($this->contenido()['asunto'] ?? 'FatherMotoSport'));
    }

    public function content(): Content
    {
        $c = $this->contenido();
        $contacto = PieDeCorreo::contacto();
        $prefijo = $this->idioma() === 'es' ? '' : '/'.$this->idioma();
        $botonUrl = HtmlDeCorreo::hrefSeguro($c['boton_url'] ?? null);

        return new Content(
            view: 'emails.campana',
            with: [
                ...$this->datosDePlantilla(),
                // La página de baja en el mismo idioma del correo (la firma es solo del id).
                'urlBaja' => BajaDePromociones::urlPagina((clone $this->destinatario)->forceFill(['locale' => $this->idioma()])),
                'asunto' => $this->envelope()->subject,
                'preheader' => (string) ($c['preheader'] ?? ''),
                'titular' => (string) ($c['titular'] ?? ''),
                'cuerpoHtml' => HtmlDeCorreo::conEstilos(HtmlDeCorreo::limpiar($c['cuerpo'] ?? '')),
                // imagen_url_previa: solo en la vista previa en vivo (imagen recién subida).
                'imagen' => $this->campana['imagen_url_previa'] ?? ImagenDeCampana::url($this->campana['imagen'] ?? null),
                'imagenAlt' => (string) ($c['imagen_alt'] ?? ''),
                'botonTexto' => $botonUrl ? (string) ($c['boton_texto'] ?? '') : '',
                'botonUrl' => $botonUrl,
                'urlTienda' => rtrim((string) config('app.frontend_url'), '/').$prefijo,
                'textoTienda' => __('marketing.visit_store', [], $this->idioma()),
                'emailContacto' => $contacto['email'],
                'textoContacto' => __('marketing.category_discount.contact', ['email' => $contacto['email']], $this->idioma()),
                'whatsapp' => $contacto['whatsapp'] !== null ? "https://wa.me/{$contacto['whatsapp']}" : null,
            ],
        );
    }
}
