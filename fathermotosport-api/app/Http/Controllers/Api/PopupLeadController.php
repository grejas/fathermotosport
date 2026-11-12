<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PopupLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PopupLeadController extends Controller
{
    public function __construct(private PopupLeadService $leads) {}

    /**
     * Registra el email del popup y devuelve el cupón del cliente.
     *
     * 201 nuevo · 200 ya registrado con cupón vigente · 409 ya usó o venció su cupón ·
     * 429 demasiados registros desde la misma conexión.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{7,20}$/'],
            'country' => ['nullable', 'string', 'size:2'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
            'consent' => ['accepted'],
            'source' => ['nullable', 'array'],
            'source.*' => ['nullable', 'string', 'max:200'],
            // Trampa para bots: un campo oculto que una persona nunca llena.
            'website' => ['prohibited'],
        ]);

        $resultado = $this->leads->register($datos, $request->ip());

        return match ($resultado['status']) {
            'limited' => response()->json(['status' => 'limited'], 429),
            'already_used' => response()->json(['status' => 'already_used'], 409),
            default => response()->json([
                'status' => $resultado['status'],
                'code' => $resultado['lead']->coupon->code,
                'percent' => (int) $resultado['lead']->coupon->value,
                'expires_at' => $resultado['lead']->coupon->expires_at?->toIso8601String(),
                'minimum_amount' => (float) $resultado['lead']->coupon->minimum_amount,
            ], $resultado['status'] === 'new' ? 201 : 200),
        };
    }
}
