<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\PieDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Único aviso al cliente que llegó al checkout y no completó el pago. Lo envía el
 * comando payments:recover-pending, en el idioma en que se hizo el pedido.
 *
 * Plantilla propia en tablas y estilos en línea (no el layout base, que usa divs y
 * clases): es la que más tiene que verse bien en Outlook y en el modo oscuro de Gmail.
 */
class PaymentRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CONTACTO = 'contacto@fathermotosport.com';

    /**
     * Insignias de los métodos que acepta la tienda: archivo => texto alternativo.
     * PNG (no SVG: Gmail y Outlook no los muestran), servidos por el frontend desde
     * public/email/payment-badges/. Apple Pay y Google Pay no están: dependen de que
     * estén activados en el panel de Stripe, y eso no se puede confirmar desde el código.
     */
    public const INSIGNIAS = [
        'paypal' => 'PayPal',
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
    ];

    public function __construct(public Order $order)
    {
        $this->locale($order->idioma());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('payment_recovery.subject', [], $this->order->idioma()),
        );
    }

    public function content(): Content
    {
        $idioma = $this->order->idioma();
        $recursos = rtrim((string) (config('app.email_assets_url') ?: config('app.frontend_url')), '/');

        return new Content(
            view: 'emails.payment-recovery',
            with: [
                // images: la miniatura sale de primary_image; se carga junto para no
                // hacer una consulta por producto.
                'order' => $this->order->loadMissing(['items.variant.product.images', 'address', 'user']),
                'nombre' => $this->nombre(),
                'urlPago' => $this->order->urlParaPagar(),
                'urlTienda' => rtrim((string) config('app.frontend_url'), '/'),
                'contacto' => self::CONTACTO,
                'emailLocale' => $idioma,
                'insignias' => collect(self::INSIGNIAS)
                    ->map(fn (string $alt, string $archivo) => ['src' => "{$recursos}/email/payment-badges/{$archivo}.png", 'alt' => $alt])
                    ->values()
                    ->all(),
                'pie' => PieDeCorreo::textos($idioma),
                'direccionTienda' => PieDeCorreo::DIRECCION,
                'whatsapp' => PieDeCorreo::WHATSAPP,
            ],
        );
    }

    /**
     * Nombre de pila para el saludo: el de la cuenta, o el que escribió en la dirección.
     * Un pedido Express sin datos todavía lleva un marcador, que no es un nombre.
     */
    private function nombre(): ?string
    {
        $completo = $this->order->user?->first_name ?: $this->order->address?->full_name;

        if (blank($completo) || $completo === Order::DATO_PENDIENTE) {
            return null;
        }

        return strtok(trim($completo), ' ') ?: null;
    }
}
