<?php

namespace App\Mail;

use App\Models\Category;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\DestacadosDelAviso;
use App\Support\ImagenDeCorreo;
use App\Support\PieDeCorreo;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de un descuento de categoría: titular "-X % en Categoría", hasta 4 productos con
 * el precio de lista tachado y el final (PrecioFinal), botón a la categoría y, solo si el
 * descuento tiene fecha de fin, "Válido hasta el ...". Sin fecha de fin no hay urgencia.
 * No es un cupón: el descuento ya está en los precios de la web. Plantilla propia en
 * tablas (emails.aviso-descuento), con la baja y las cabeceras de CorreoPromocional.
 *
 * $aviso son los datos congelados al crear el envío (AvisoDescuento::datos):
 * category_id, category_name, percent, starts_at, ends_at (ISO-8601 o null), has_exclusions.
 * La imagen de la categoría y los productos se leen al armar el correo.
 */
class CorreoAvisoDescuento extends CorreoPromocional
{
    public function __construct(User $destinatario, public array $aviso, string $referencia)
    {
        parent::__construct($destinatario, $referencia);
    }

    private function t(string $clave, array $datos = []): string
    {
        return __("marketing.category_discount.{$clave}", $datos, $this->idioma());
    }

    /** "15 %" en español, "15%" en portugués e inglés; "7,5" con coma en es/pt. */
    public function porcentajeTexto(): string
    {
        $numero = rtrim(rtrim(number_format((float) $this->aviso['percent'], 2, '.', ''), '0'), '.');
        if ($this->idioma() !== 'en') {
            $numero = str_replace('.', ',', $numero);
        }

        return $this->idioma() === 'es' ? "{$numero} %" : "{$numero}%";
    }

    private function fin(): ?CarbonImmutable
    {
        $iso = $this->aviso['ends_at'] ?? null;

        return $iso ? CarbonImmutable::parse($iso)->setTimezone(config('marketing.timezone', 'America/La_Paz'))->locale($this->idioma()) : null;
    }

    /**
     * "15 de marzo de 2027" / "March 15, 2027": solo la fecha (día de La Paz), sin hora ni
     * zona. Como en el asunto, un fin a las 00:00 es el final del día anterior.
     */
    public function fechaFinTexto(): ?string
    {
        $fin = $this->fin()?->subSecond();
        if (! $fin) {
            return null;
        }

        return $this->idioma() === 'en' ? $fin->isoFormat('MMMM D, YYYY') : $fin->isoFormat('D [de] MMMM [de] YYYY');
    }

    /**
     * "5 de noviembre" / "November 5", para el asunto. Un fin a las 00:00 es el final del
     * día anterior: se resta un segundo para no prometer un día de más.
     */
    public function fechaFinCorta(): ?string
    {
        $fin = $this->fin()?->subSecond();
        if (! $fin) {
            return null;
        }

        return $this->idioma() === 'en' ? $fin->isoFormat('MMMM D') : $fin->isoFormat('D [de] MMMM');
    }

    private function urlWeb(string $ruta, array $consulta = []): string
    {
        $prefijo = $this->idioma() === 'es' ? '' : '/'.$this->idioma();

        return rtrim((string) config('app.frontend_url'), '/').$prefijo.$ruta.($consulta ? '?'.http_build_query($consulta) : '');
    }

    /** Catálogo de la web filtrado por la categoría, en el idioma del cliente. */
    public function urlCategoria(): string
    {
        return $this->urlWeb('/catalog', ['category' => $this->aviso['category_id']]);
    }

    private function categoria(): ?Category
    {
        return Category::find($this->aviso['category_id'] ?? null);
    }

    /** Hasta 4 productos con descuento, listos para la plantilla. Vacío si no hay. */
    public function productos(?Category $categoria = null): array
    {
        $categoria ??= $this->categoria();
        if (! $categoria) {
            return [];
        }

        return app(DestacadosDelAviso::class)->de($categoria)->map(fn (array $d) => [
            'nombre' => $d['producto']->name,
            'imagen' => ImagenDeCorreo::url($d['producto']->primary_image, comprobarArchivo: true),
            'precio' => $d['precio'],
            'precio_final' => $d['precio_final'],
            'url' => $this->urlWeb('/product/'.rawurlencode($d['producto']->slug)),
        ])->all();
    }

    private function datosDeTexto(): array
    {
        return ['category' => $this->aviso['category_name'], 'percent' => $this->porcentajeTexto()];
    }

    public function envelope(): Envelope
    {
        $corta = $this->fechaFinCorta();

        return new Envelope(subject: $corta
            ? $this->t('subject_until', [...$this->datosDeTexto(), 'date' => $corta])
            : $this->t('subject', $this->datosDeTexto()));
    }

    public function content(): Content
    {
        $categoria = $this->categoria();
        $contacto = PieDeCorreo::contacto();
        $fin = $this->fechaFinTexto();

        return new Content(
            view: 'emails.aviso-descuento',
            with: [
                ...$this->datosDePlantilla(),
                'asunto' => $this->envelope()->subject,
                'preheader' => $this->t('preheader'),
                'titular' => $this->t('headline', $this->datosDeTexto()),
                'porcentajeGrande' => '-'.$this->porcentajeTexto(),
                'subtitulo' => $this->t('subtitle'),
                'textoVigencia' => $fin ? $this->t('valid_until', ['date' => $fin]) : null,
                'excepto' => ! empty($this->aviso['has_exclusions']) ? $this->t('except') : null,
                'boton' => $this->t('button', ['category' => $this->aviso['category_name']]),
                'url' => $this->urlCategoria(),
                'urlTienda' => $this->urlWeb(''),
                'textoTienda' => $this->t('store_link'),
                'banner' => $categoria ? ImagenDeCorreo::url(ProductImage::publicUrl($categoria->image_url), comprobarArchivo: true) : null,
                'nombreCategoria' => $this->aviso['category_name'],
                'productos' => $this->productos($categoria),
                'tituloProductos' => $this->t('featured'),
                'antes' => $this->t('before'),
                'ahora' => $this->t('now'),
                'verProducto' => $this->t('view_product'),
                'confianza' => [PieDeCorreo::textos($this->idioma())['envio'], $this->t('secure_payment')],
                'emailContacto' => $contacto['email'],
                'textoContacto' => $this->t('contact', ['email' => $contacto['email']]),
                'whatsapp' => $contacto['whatsapp'] !== null ? "https://wa.me/{$contacto['whatsapp']}" : null,
            ],
        );
    }
}
