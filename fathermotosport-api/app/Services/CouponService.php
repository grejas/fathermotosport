<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\OrderCoupon;
use App\Models\User;
use Illuminate\Support\Str;

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
     * Verifica la validez completa de un cupón para un monto dado.
     *
     * @return array{valid: bool, message: string, coupon: ?Coupon, discount: float}
     */
    public function validateCoupon(string $code, float $amount): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return $this->result(false, 'El cupón no existe.');
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
     */
    public function applyCoupon(string $code, string $orderId, float $amount, ?User $user = null): ?OrderCoupon
    {
        $validation = $this->validateCoupon($code, $amount);

        if (! $validation['valid']) {
            return null;
        }

        /** @var Coupon $coupon */
        $coupon = $validation['coupon'];

        $orderCoupon = OrderCoupon::create([
            'order_id' => $orderId,
            'coupon_id' => $coupon->id,
            'discount_applied' => $validation['discount'],
        ]);

        $coupon->increment('used_count');

        // Marca el descuento de fidelidad como usado si corresponde al cupón de bienvenida.
        if ($user && $coupon->type === 'fixed' && (float) $coupon->value === self::WELCOME_AMOUNT && ! $user->loyalty_discount_used) {
            $user->forceFill(['loyalty_discount_used' => true])->save();
        }

        return $orderCoupon;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'FMS-' . strtoupper(Str::random(6));
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
