<?php

namespace App\Mail;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Coupon $coupon)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Bienvenido a FatherMotoSport — Tu cupón de $5 te espera',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'coupon' => $this->coupon,
            ],
        );
    }
}
