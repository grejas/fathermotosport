<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?User $user = null)
    {
        // Si no se pasa el usuario, usar el del pedido (puede ser null en guest).
        $this->user = $user ?? $order->user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Tu pedido {$this->order->order_number} fue confirmado — FatherMotoSport",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmed',
            with: [
                'order' => $this->order->loadMissing(['items.variant.product', 'address']),
                'user' => $this->user,
            ],
        );
    }
}
