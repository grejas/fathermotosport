<?php

namespace App\Services;

use App\Models\PopupLead;
use Illuminate\Support\Facades\DB;

/**
 * Registra los contactos del popup y les entrega su cupón.
 *
 * Reglas:
 * - Un email = un cupón. Si el email ya está registrado y su cupón sigue vigente y sin
 *   usar, se le devuelve el mismo (no se puede "tirar de nuevo" para sacar 25 %).
 *   Si ya lo usó o venció, no recibe otro.
 * - Máximo N contactos nuevos por IP y por día (config popup.max_per_ip_per_day).
 */
class PopupLeadService
{
    public function __construct(private CouponService $coupons) {}

    /**
     * @param  array{email:string, whatsapp?:?string, country?:?string, locale?:?string, source?:?array}  $datos
     * @return array{status:'new'|'existing'|'already_used'|'limited', lead:?PopupLead}
     */
    public function register(array $datos, ?string $ip): array
    {
        $email = mb_strtolower(trim($datos['email']));
        $ipHash = $ip ? hash('sha256', $ip.'|'.config('app.key')) : null;

        $existente = PopupLead::with('coupon')->where('email', $email)->first();
        if ($existente) {
            // Completar el WhatsApp si antes no lo dio.
            if (! $existente->whatsapp && ! empty($datos['whatsapp'])) {
                $existente->update(['whatsapp' => $datos['whatsapp']]);
            }
            $cupon = $existente->coupon;
            if ($cupon && $cupon->isValid()) {
                return ['status' => 'existing', 'lead' => $existente];
            }

            return ['status' => 'already_used', 'lead' => $existente];
        }

        if ($ipHash && $this->nuevosHoyDesde($ipHash) >= config('popup.max_per_ip_per_day')) {
            return ['status' => 'limited', 'lead' => null];
        }

        $lead = DB::transaction(function () use ($datos, $email, $ipHash) {
            $porcentaje = $this->sortearPorcentaje();
            $cupon = $this->coupons->generatePopupCoupon(
                $porcentaje,
                config('popup.valid_days'),
                config('popup.minimum_amount'),
            );

            return PopupLead::create([
                'email' => $email,
                'whatsapp' => $datos['whatsapp'] ?? null,
                'country' => isset($datos['country']) ? strtoupper($datos['country']) : null,
                'locale' => $datos['locale'] ?? null,
                'coupon_id' => $cupon->id,
                'percent' => $porcentaje,
                'source' => $datos['source'] ?? null,
                'consent' => true,
                'consent_at' => now(),
                'ip_hash' => $ipHash,
            ])->load('coupon');
        });

        return ['status' => 'new', 'lead' => $lead];
    }

    /** Porcentaje al azar según los pesos de config/popup.php. */
    public function sortearPorcentaje(): int
    {
        $pesos = config('popup.weights');
        $total = array_sum($pesos);
        $tiro = random_int(1, $total);

        foreach ($pesos as $porcentaje => $peso) {
            $tiro -= $peso;
            if ($tiro <= 0) {
                return (int) $porcentaje;
            }
        }

        return (int) array_key_first($pesos);
    }

    private function nuevosHoyDesde(string $ipHash): int
    {
        return PopupLead::where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDay())->count();
    }
}
