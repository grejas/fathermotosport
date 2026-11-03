<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\OrderCoupon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Monto fijo del cupón de bienvenida (descuento de $5 al registrarse).
     */
    public const WELCOME_AMOUNT = 5.00;

    /**
     * Genera un cupón único de $5 fijo para un usuario recién registrado.
     */
    public function generateWelcomeCoupon(string $userId): Coupon
    {
        return Coupon::create([
            'user_id' => $userId,
            'code' => $this->uniqueCode(),
            'type' => 'fixed',
            'value' => self::WELCOME_AMOUNT,
            'minimum_amount' => 0,
            'max_uses' => 1,
            'used_count' => 0,
            'start_date' => now(),
            'end_date' => now()->addDay(),        // nivel fecha
            'expires_at' => now()->addHours(24),  // vencimiento exacto: 24 horas
            'is_active' => true,
        ]);
    }

    /**
     * Cupón de cumpleaños: porcentaje, un solo uso, con dueño (solo lo usa ese cliente
     * con sesión iniciada) y código propio BDAY-XXXXXX. Vence en $vence (fin del día en
     * La Paz, calculado por quien lo pide).
     */
    public function generateBirthdayCoupon(string $userId, float $percent, \DateTimeInterface $vence): Coupon
    {
        return Coupon::create([
            'user_id' => $userId,
            'code' => $this->uniqueCode('BDAY-'),
            'type' => 'percentage',
            'value' => $percent,
            'minimum_amount' => 0,
            'max_uses' => 1,
            'used_count' => 0,
            'start_date' => now(),
            'end_date' => $vence,
            'expires_at' => $vence,
            'is_active' => true,
        ]);
    }

    /**
     * Verifica la validez completa de un cupón para un monto dado.
     *
     * @return array{valid: bool, message: string, coupon: ?Coupon, discount: float}
     */
    public function validateCoupon(string $code, float $amount, ?User $user = null, bool $bloquear = false): array
    {
        // $bloquear: dentro de la transacción del pedido, la fila queda bloqueada hasta
        // el final, para que dos pedidos simultáneos no usen el mismo cupón de un uso.
        $coupon = Coupon::where('code', $code)->when($bloquear, fn ($q) => $q->lockForUpdate())->first();

        if (! $coupon) {
            return $this->result(false, 'El cupón no existe.');
        }

        // Cupón personal (bienvenida, cumpleaños): solo su dueño, con sesión iniciada.
        // Los cupones sin dueño siguen sirviendo para cualquiera.
        if ($coupon->user_id !== null && $coupon->user_id !== $user?->id) {
            return $this->result(false, __('registro.coupon_owner'), $coupon);
        }

        if ($coupon->isExpired()) {
            return $this->result(false, 'Este cupón ha expirado.', $coupon);
        }

        if (! $coupon->isValid()) {
            return $this->result(false, 'El cupón no está vigente o ya alcanzó su límite de usos.', $coupon);
        }

        if ($amount < (float) $coupon->minimum_amount) {
            return $this->result(
                false,
                "El monto mínimo para este cupón es \${$coupon->minimum_amount}.",
                $coupon
            );
        }

        return $this->result(true, 'Cupón válido.', $coupon, $this->discountFor($coupon, $amount));
    }

    /**
     * Calcula el descuento que aplica un cupón sobre un monto.
     */
    public function discountFor(Coupon $coupon, float $amount): float
    {
        $discount = $coupon->type === 'percentage'
            ? $amount * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        // El descuento nunca puede superar el subtotal.
        return round(min($discount, $amount), 2);
    }

    /**
     * Aplica el cupón a un pedido: registra en order_coupons e incrementa used_count.
     * Si es el cupón de bienvenida ($5 fijo), marca loyalty_discount_used en el usuario.
     *
     * Va dentro de la transacción del pedido. El uso se suma con una actualización
     * condicional (solo si quedan usos): si otro pedido lo gastó entre la validación y
     * este punto, se lanza el error y la transacción entera se deshace.
     */
    public function applyCoupon(string $code, string $orderId, float $amount, ?User $user = null): OrderCoupon
    {
        $validation = $this->validateCoupon($code, $amount, $user, bloquear: true);

        if (! $validation['valid']) {
            throw ValidationException::withMessages(['coupon_code' => [$validation['message']]]);
        }

        /** @var Coupon $coupon */
        $coupon = $validation['coupon'];

        $usado = Coupon::whereKey($coupon->id)
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
            ->increment('used_count');

        if ($usado === 0) {
            throw ValidationException::withMessages(['coupon_code' => [__('registro.coupon_used')]]);
        }

        $orderCoupon = OrderCoupon::create([
            'order_id' => $orderId,
            'coupon_id' => $coupon->id,
            'discount_applied' => $validation['discount'],
        ]);

        // Marca el descuento de fidelidad como usado si corresponde al cupón de bienvenida.
        if ($user && $coupon->type === 'fixed' && (float) $coupon->value === self::WELCOME_AMOUNT && ! $user->loyalty_discount_used) {
            $user->forceFill(['loyalty_discount_used' => true])->save();
        }

        return $orderCoupon;
    }

    private function uniqueCode(string $prefijo = 'FMS-'): string
    {
        do {
            $code = $prefijo.strtoupper(Str::random(6));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    private function result(bool $valid, string $message, ?Coupon $coupon = null, float $discount = 0.0): array
    {
        return [
            'valid' => $valid,
            'message' => $message,
            'coupon' => $coupon,
            'discount' => $discount,
        ];
    }
}
