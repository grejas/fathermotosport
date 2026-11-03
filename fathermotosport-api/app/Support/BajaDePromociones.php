<?php

namespace App\Support;

use App\Models\MailUnsubscribe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Baja de los correos promocionales (nunca de los transaccionales).
 *
 * El enlace lleva el id del cliente y una firma HMAC-SHA256 con la APP_KEY: no caduca
 * (un correo viejo tiene que seguir sirviendo para darse de baja) y no deja adivinar
 * la de otro cliente.
 */
final class BajaDePromociones
{
    public static function firma(User $user): string
    {
        return hash_hmac('sha256', 'baja-promociones|'.$user->id, (string) config('app.key'));
    }

    /** Cliente del enlace, o null si la firma no corresponde. */
    public static function usuarioDelEnlace(?string $id, ?string $firma): ?User
    {
        if (! is_string($id) || ! is_string($firma) || $id === '' || $firma === '') {
            return null;
        }

        $user = User::withTrashed()->find($id);

        return $user && hash_equals(self::firma($user), $firma) ? $user : null;
    }

    /** Página de la web con el botón de confirmar (la baja no ocurre al abrir el enlace). */
    public static function urlPagina(User $user): string
    {
        $idioma = in_array($user->locale, ['pt', 'en'], true) ? '/'.$user->locale : '';

        return rtrim((string) config('app.frontend_url'), '/').$idioma.'/baja?'.http_build_query([
            'u' => $user->id,
            't' => self::firma($user),
        ]);
    }

    /** Destino del POST de un clic (List-Unsubscribe-Post, RFC 8058). */
    public static function urlUnClic(User $user): string
    {
        return url('/api/v1/promos/unsubscribe').'?'.http_build_query([
            'u' => $user->id,
            't' => self::firma($user),
        ]);
    }

    /**
     * Da de baja y la registra. Idempotente: si ya estaba de baja no cambia la fecha ni
     * duplica el registro. true si esta llamada fue la que la dio de baja.
     */
    public static function darDeBaja(User $user, string $origen, ?string $referencia = null): bool
    {
        return DB::transaction(function () use ($user, $origen, $referencia) {
            $actualizadas = User::withTrashed()
                ->whereKey($user->id)
                ->whereNull('promo_unsubscribed_at')
                ->update(['promo_unsubscribed_at' => now()]);

            if ($actualizadas === 0) {
                return false;
            }

            MailUnsubscribe::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'source' => $origen,
                'reference' => $referencia ? mb_substr($referencia, 0, 100) : null,
            ]);

            return true;
        });
    }

    /** "juan.perez@gmail.com" => "ju•••••••@gmail.com", para mostrarlo en la página. */
    public static function correoOculto(string $email): string
    {
        [$local, $dominio] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 2);

        return $visible.str_repeat('•', max(3, mb_strlen($local) - 2)).'@'.$dominio;
    }
}
