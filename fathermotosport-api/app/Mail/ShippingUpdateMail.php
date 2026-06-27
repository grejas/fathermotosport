<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShippingUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $trackingNumber = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu pedido {$this->order->order_number} va en camino - FatherMotoSport",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.shipping',
            with: [
                'order' => $this->order,
                'trackingNumber' => $this->trackingNumber,
            ],
        );
    }
}
