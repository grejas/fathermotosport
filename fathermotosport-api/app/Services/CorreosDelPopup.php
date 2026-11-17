<?php

namespace App\Services;

use App\Mail\CorreoCuponPopup;
use App\Models\PopupLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos automáticos de los contactos del popup:
 *  1. Bienvenida con el cupón, a los pocos minutos de suscribirse (solo si se suscribió en
 *     los últimos 3 días: si el sistema estuvo apagado no se manda un "bienvenido" tardío).
 *  2. Recordatorio cuando faltan menos de N horas para que venza y aún no lo usó.
 * Nunca a quien se dio de baja (del popup o como cliente), ni a correos que rebotaron o
 * marcaron spam. Cada correo sale una sola vez: se marca antes de enviar y se desmarca si
 * falla, para reintentar en la siguiente pasada.
 */
class CorreosDelPopup
{
    /** @return array{bienvenidas:int, recordatorios:int, fallidos:int} */
    public function procesar(int $limite = 30): array
    {
        $r = ['bienvenidas' => 0, 'recordatorios' => 0, 'fallidos' => 0];
        if (! config('popup.emails_enabled', true)) {
            return $r;
        }

        $base = fn () => PopupLead::query()
            ->with('coupon')
            ->where('consent', true)
            ->whereNull('unsubscribed_at')
            ->whereNotNull('coupon_id');

        $bienvenidas = $base()
            ->whereNull('welcome_sent_at')
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('id')->limit($limite)->get();

        foreach ($bienvenidas as $lead) {
            $this->enviar($lead, CorreoCuponPopup::BIENVENIDA, 'welcome_sent_at', $r, 'bienvenidas');
        }

        $horas = (int) config('popup.reminder_hours_before', 48);
        $recordatorios = $base()
            ->whereNull('reminder_sent_at')
            ->whereNotNull('welcome_sent_at')
            ->where('welcome_sent_at', '<=', now()->subHours(12))
            ->whereHas('coupon', fn ($q) => $q
                ->where('is_active', true)
                ->whereColumn('used_count', '<', 'max_uses')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<=', now()->addHours($horas)))
            ->orderBy('id')->limit($limite)->get();

        foreach ($recordatorios as $lead) {
            $this->enviar($lead, CorreoCuponPopup::RECORDATORIO, 'reminder_sent_at', $r, 'recordatorios');
        }

        return $r;
    }

    /** Si el correo está de baja como cliente, rebotó o marcó spam, no se le escribe. */
    public function bloqueado(string $email): bool
    {
        $email = mb_strtolower(trim($email));

        $usuario = User::query()->whereRaw('LOWER(email) = ?', [$email])
            ->where(fn ($q) => $q->whereNotNull('promo_unsubscribed_at')
                ->orWhereNotNull('email_bounced_at')
                ->orWhereNotNull('email_complained_at'))
            ->exists();

        return $usuario || DB::table('mail_unsubscribes')->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function enviar(PopupLead $lead, string $tipo, string $columna, array &$r, string $contador): void
    {
        if ($this->bloqueado($lead->email)) {
            $lead->forceFill([$columna => now()])->save(); // no se vuelve a evaluar

            return;
        }

        // Se marca primero: si dos pasadas coinciden, solo una lo envía.
        $marcado = PopupLead::whereKey($lead->getKey())->whereNull($columna)->update([$columna => now()]);
        if (! $marcado) {
            return;
        }

        try {
            Mail::to($lead->email)->send(new CorreoCuponPopup($lead, $tipo));
            $r[$contador]++;
        } catch (Throwable $e) {
            PopupLead::whereKey($lead->getKey())->update([$columna => null]);
            $r['fallidos']++;
            Log::warning('popup: no se pudo enviar el correo', ['lead' => $lead->getKey(), 'tipo' => $tipo, 'error' => $e->getMessage()]);
        }
    }
}
