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

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚚 Tu pedido {$this->order->order_number} fue despachado — FatherMotoSport",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shipping-update',
            with: [
                'order' => $this->order->loadMissing('address'),
                'trackingNumber' => $this->trackingNumber,
                'carrier' => $this->carrier,
            ],
        );
    }
}
