<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    /**
     * Verifica si un cupón es válido para el monto dado.
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Ruta pública: si viene el token, el cliente puede usar sus cupones personales.
        $result = $this->coupons->validateCoupon($data['code'], (float) $data['amount'], auth('sanctum')->user());

        return response()->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon' => $result['valid'] ? [
                'code' => $result['coupon']->code,
                'type' => $result['coupon']->type,
                'value' => $result['coupon']->value,
            ] : null,
        ], $result['valid'] ? 200 : 422);
    }
}
