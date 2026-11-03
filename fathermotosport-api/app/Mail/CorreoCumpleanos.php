<?php

namespace App\Mail;

use App\Models\User;
use App\Support\PrimerNombre;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de cumpleaños: saludo, deseo, el cupón de regalo (porcentaje, código y
 * vencimiento) y el botón que lleva al carrito con el cupón (?cupon=CODIGO). En el idioma
 * del cliente, con la plantilla promocional (baja al pie y List-Unsubscribe).
 *
 * Recibe el código y no el cupón para que el envío de prueba no tenga que crear uno.
 */
class CorreoCumpleanos extends CorreoPromocional
{
    public function __construct(
        User $destinatario,
        public string $codigo,
        public float $porcentaje,
        public CarbonInterface $vence,
        int $anio,
    ) {
        parent::__construct($destinatario, "birthday:{$anio}");
    }

    private function t(string $clave, array $datos = []): string
    {
        return __("marketing.birthday.{$clave}", $datos, $this->idioma());
    }

    /** "5 %" en español, "5%" en portugués e inglés; "7,5" con coma en es/pt. */
    public function porcentajeTexto(): string
    {
        $numero = rtrim(rtrim(number_format($this->porcentaje, 2, '.', ''), '0'), '.');
        if ($this->idioma() !== 'en') {
            $numero = str_replace('.', ',', $numero);
        }

        return $this->idioma() === 'es' ? "{$numero} %" : "{$numero}%";
    }

    /** "6 de noviembre de 2026" / "6 de novembro de 2026" / "November 6, 2026", hora de La Paz. */
    public function venceTexto(): string
    {
        $fecha = CarbonImmutable::instance($this->vence)->setTimezone(config('marketing.timezone', 'America/La_Paz'))->locale($this->idioma());

        return $this->idioma() === 'en' ? $fecha->isoFormat('MMMM D, YYYY') : $fecha->isoFormat('D [de] MMMM [de] YYYY');
    }

    /** Carrito de la web con el cupón: el cupón por URL de la fase 1 lo aplica con sesión. */
    public function urlCarrito(): string
    {
        $prefijo = $this->idioma() === 'es' ? '' : '/'.$this->idioma();

        return rtrim((string) config('app.frontend_url'), '/').$prefijo.'/cart?'.http_build_query(['cupon' => $this->codigo]);
    }

    /**
     * Primer nombre de la cuenta (users.first_name: "gerson rejas" → "Gerson"), o null si
     * no hay uno utilizable: entonces el saludo va sin nombre ("¡Feliz cumpleaños!"). Solo
     * first_name: con él vacío no se saluda por el apellido. Ver PrimerNombre.
     */
    public function nombre(): ?string
    {
        return PrimerNombre::de($this->destinatario->first_name);
    }

    /** subject/greeting con el nombre, o subject_plain/greeting_plain sin él. */
    private function conNombre(string $clave): string
    {
        $nombre = $this->nombre();

        return $nombre !== null ? $this->t($clave, ['name' => $nombre]) : $this->t("{$clave}_plain");
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->conNombre('subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cumpleanos',
            with: [
                ...$this->datosDePlantilla(),
                'preheader' => $this->t('preheader', ['percent' => $this->porcentajeTexto()]),
                'saludo' => $this->conNombre('greeting'),
                'deseo' => $this->t('wish'),
                'celebra' => $this->t('celebrate'),
                'etiquetaCupon' => $this->t('coupon_label'),
                'descuento' => $this->t('discount', ['percent' => $this->porcentajeTexto()]),
                'codigo' => $this->codigo,
                'textoVence' => $this->t('expires', ['date' => $this->venceTexto()]),
                'boton' => $this->t('button'),
                'url' => $this->urlCarrito(),
                'personal' => $this->t('personal'),
                'pistaCodigo' => $this->t('code_hint'),
            ],
        );
    }
}
