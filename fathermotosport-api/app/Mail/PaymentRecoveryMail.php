<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\ImagenDeCorreo;
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
 *
 * Es un correo transaccional y tiene que parecerlo, o Gmail lo manda a Promociones:
 * sin insignias de pago, sin textos comerciales y sin cabeceras de lista de correo
 * (List-Unsubscribe y similares). No definir headers() acá.
 */
class PaymentRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CONTACTO = 'contacto@fathermotosport.com';

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

        // images: la miniatura sale de primary_image; se carga junto para no hacer una
        // consulta por producto.
        $order = $this->order->loadMissing(['items.variant.product.images', 'address', 'user']);

        return new Content(
            view: 'emails.payment-recovery',
            with: [
                'order' => $order,
                // Por ítem: URL lista para el correo, o null si no hay una imagen que
                // se pueda mostrar (ver ImagenDeCorreo). Null = sin columna de miniatura.
                'miniaturas' => $order->items->mapWithKeys(fn ($item) => [
                    $item->id => ImagenDeCorreo::url($item->variant?->product?->primary_image, comprobarArchivo: true),
                ])->all(),
                'nombre' => $this->nombre(),
                'urlPago' => $this->order->urlParaPagar(),
                'urlTienda' => rtrim((string) config('app.frontend_url'), '/'),
                'contacto' => self::CONTACTO,
                'emailLocale' => $idioma,
                'nombreTienda' => PieDeCorreo::TIENDA,
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
