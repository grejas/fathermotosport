<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\VisorColor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Le pide al cliente que confirme color de visor (cascos), talla y dirección antes de
 * despachar. Sale SIEMPRE de la casilla de ventas (config mail.ventas) y las respuestas
 * llegan ahí: nunca desde un correo personal.
 */
class ConfirmarDatosEnvioMail extends Mailable
{
    use Queueable, SerializesModels;

    /** `nota`: línea opcional al inicio (p. ej. aclarar que este es el correo oficial). */
    public function __construct(public Order $order, public ?string $nota = null) {}

    public function envelope(): Envelope
    {
        $ventas = new Address(config('mail.ventas.address'), config('mail.ventas.name'));

        return new Envelope(
            from: $ventas,
            replyTo: [$ventas],
            subject: __('confirmar_datos_envio.subject', ['order' => $this->order->order_number], $this->order->idioma()),
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['items.variant.product.category', 'items.variant.product.visorColors', 'address']);

        $items = $order->items->map(function ($item) {
            $product = $item->variant?->product;
            $esCasco = $product?->category?->slug === 'cascos';
            $visores = collect();
            if ($esCasco) {
                $propios = $product->visorColors->where('is_active', true)->sortBy('sort_order');
                $visores = ($propios->isNotEmpty() ? $propios : VisorColor::active()->orderBy('sort_order')->get())->pluck('name');
            }

            return [
                'name' => $product?->name ?? 'Producto',
                'slug' => $product?->slug,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'es_casco' => $esCasco,
                'visor' => $item->visor_color,
                'visores' => $visores->values()->all(),
            ];
        });

        return new Content(
            text: 'emails.confirmar-datos-envio',
            with: [
                'order' => $order,
                'a' => $order->address,
                'items' => $items,
                'l' => $order->idioma(),
                'web' => rtrim(config('app.frontend_url', 'https://fathermotosport.com'), '/'),
                'ventas' => config('mail.ventas.address'),
                'nota' => $this->nota,
            ],
        );
    }
}
