<?php

namespace App\Mail;

use App\Models\PopupLead;
use App\Support\BajaDelPopup;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Correos automáticos del popup, en el idioma del contacto:
 *  - bienvenida: el cupón que acaba de revelar, para que no lo pierda.
 *  - recordatorio: el cupón vence pronto y todavía no lo usó.
 * Plantilla emails.cupon-popup sobre el layout promocional, con baja visible y las
 * cabeceras List-Unsubscribe de un clic.
 */
class CorreoCuponPopup extends Mailable
{
    use Queueable, SerializesModels;

    public const BIENVENIDA = 'bienvenida';

    public const RECORDATORIO = 'recordatorio';

    public function __construct(public PopupLead $lead, public string $tipo)
    {
        $this->locale($lead->idioma());
    }

    private function t(string $clave, array $datos = []): string
    {
        return __("marketing.popup_coupon.{$clave}", $datos, $this->lead->idioma());
    }

    private function datos(): array
    {
        return ['percent' => $this->lead->percent.'%', 'code' => $this->lead->coupon?->code];
    }

    private function vence(): ?CarbonImmutable
    {
        $fecha = $this->lead->coupon?->expires_at;

        return $fecha ? CarbonImmutable::parse($fecha)->setTimezone(config('marketing.timezone', 'America/La_Paz'))->locale($this->lead->idioma()) : null;
    }

    public function fechaVence(): ?string
    {
        $v = $this->vence();
        if (! $v) {
            return null;
        }

        return $this->lead->idioma() === 'en' ? $v->isoFormat('MMMM D, h:mm A') : $v->isoFormat('D [de] MMMM, HH:mm');
    }

    /** La web en el idioma del contacto, con el cupón y la UTM del correo. */
    public function urlTienda(): string
    {
        $idioma = $this->lead->idioma();
        $prefijo = $idioma === 'es' ? '' : '/'.$idioma;

        return rtrim((string) config('app.frontend_url'), '/').$prefijo.'/catalog?'.http_build_query([
            'cupon' => $this->lead->coupon?->code,
            'utm_source' => 'email',
            'utm_medium' => 'popup',
            'utm_campaign' => $this->tipo === self::BIENVENIDA ? 'cupon_bienvenida' : 'cupon_recordatorio',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->t("{$this->tipo}_subject", $this->datos()));
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.BajaDelPopup::url($this->lead).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Resend-Idempotency-Key' => "popup-{$this->tipo}:{$this->lead->getKey()}",
        ]);
    }

    public function content(): Content
    {
        $fecha = $this->fechaVence();

        return new Content(view: 'emails.cupon-popup', with: [
            'idiomaCorreo' => $this->lead->idioma(),
            'urlBaja' => BajaDelPopup::url($this->lead),
            'motivoPie' => $this->t('footer_reason'),
            'preheader' => $this->t("{$this->tipo}_preheader", $this->datos()),
            'titulo' => $this->t("{$this->tipo}_title", $this->datos()),
            'texto' => $this->t("{$this->tipo}_text", $this->datos()),
            'etiquetaCupon' => $this->t('coupon_label'),
            'descuento' => $this->t('coupon_off', $this->datos()),
            'codigo' => $this->lead->coupon?->code,
            'textoVence' => $fecha ? $this->t('expires', ['date' => $fecha]) : '',
            'boton' => $this->t("{$this->tipo}_button"),
            'url' => $this->urlTienda(),
            'pista' => $this->t('hint'),
            'beneficios' => [$this->t('benefit_shipping'), $this->t('benefit_size'), $this->t('benefit_secure')],
        ]);
    }
}
