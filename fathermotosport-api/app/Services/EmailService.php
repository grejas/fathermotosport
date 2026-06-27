<?php

namespace App\Services;

use App\Mail\OrderConfirmationMail;
use App\Mail\ShippingUpdateMail;
use App\Mail\WelcomeMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Centraliza el envío de emails transaccionales (Resend como transporte de Mail).
 * No hay notificaciones en la web: toda comunicación es por email.
 */
class EmailService
{
    /**
     * Confirmación de pedido — a todos los compradores (guest o registrados).
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $email = $order->guest_email ?: optional($order->user)->email;

        if (! $email) {
            return;
        }

        $this->dispatch($email, new OrderConfirmationMail($order));
    }

    /**
     * Email de bienvenida con cupón de $5 — solo a usuarios registrados.
     */
    public function sendWelcome(User $user, Coupon $coupon): void
    {
        $this->dispatch($user->email, new WelcomeMail($user, $coupon));
    }

    /**
     * Aviso de despacho con número de seguimiento.
     */
    public function sendShippingUpdate(Order $order, ?string $trackingNumber = null): void
    {
        $email = $order->guest_email ?: optional($order->user)->email;

        if (! $email) {
            return;
        }

        $this->dispatch($email, new ShippingUpdateMail($order, $trackingNumber));
    }

    private function dispatch(string $email, $mailable): void
    {
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            // Un fallo de email nunca debe romper el flujo de compra.
            Log::error('Error enviando email', [
                'email' => $email,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
