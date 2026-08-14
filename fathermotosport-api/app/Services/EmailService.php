<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\ShippingUpdateMail;
use App\Mail\WelcomeEmployeeMail;
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

        $this->dispatch($email, new OrderConfirmedMail($order));
    }

    /**
     * Email de bienvenida con cupón de $5 — solo a usuarios registrados.
     */
    public function sendWelcome(User $user, Coupon $coupon): void
    {
        $this->dispatch($user->email, new WelcomeMail($user, $coupon));
    }

    /**
     * Código de verificación de email (registro en dos pasos).
     */
    public function sendVerificationCode(string $email, string $code): void
    {
        $this->dispatch($email, new EmailVerificationCodeMail($code));
    }

    /**
     * Aviso de despacho con número de seguimiento.
     */
    public function sendShippingUpdate(Order $order, ?string $trackingNumber = null, ?string $carrier = null): void
    {
        $email = $order->guest_email ?: optional($order->user)->email;

        if (! $email) {
            return;
        }

        $this->dispatch($email, new ShippingUpdateMail($order, $trackingNumber, $carrier));
    }

    /**
     * Credenciales de acceso para un empleado recién creado desde el panel.
     * Devuelve true si el envío fue exitoso, para reflejarlo en la
     * notificación de Filament (un email inválido o un fallo del proveedor
     * no debe bloquear la creación del empleado, solo quedar en logs).
     */
    public function sendWelcomeEmployee(User $employee, string $temporaryPassword): bool
    {
        return $this->dispatch($employee->email, new WelcomeEmployeeMail($employee, $temporaryPassword));
    }

    private function dispatch(string $email, $mailable): bool
    {
        try {
            Mail::to($email)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            // Un fallo de email nunca debe romper el flujo que lo dispara.
            Log::error('Error enviando email', [
                'email' => $email,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
