<?php

namespace App\Mail;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $employee, public string $temporaryPassword)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🏍️ Bienvenido al equipo FatherMotoSport — Tus accesos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-employee',
            with: [
                'employee' => $this->employee,
                'password' => $this->temporaryPassword,
                'panelUrl' => Filament::getPanel('admin')->getLoginUrl(),
            ],
        );
    }
}
