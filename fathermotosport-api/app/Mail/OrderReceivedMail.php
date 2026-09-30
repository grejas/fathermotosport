<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Se envía al CREAR el pedido, cuando el pago todavía está pendiente.
 * La confirmación de pago es OrderConfirmedMail y sale solo al capturar el cobro.
 */
class OrderReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?User $user = null)
    {
        $this->user = $user ?? $order->user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Recibimos tu pedido {$this->order->order_number} — FatherMotoSport",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received',
            with: [
                'order' => $this->order->loadMissing(['items.variant.product', 'address']),
                'user' => $this->user,
            ],
        );
    }
}
