<?php

namespace App\Services;

use App\Mail\CorreoCumpleanos;
use App\Models\BirthdayEmail;
use App\Models\BirthdaySetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Correos de cumpleaños (remarketing, fase 2).
 *
 * - Un correo por cliente y por año: la fila de birthday_emails (clave única user_id +
 *   year) y su cupón se crean juntos en una transacción. Si el envío falla o el cupo de
 *   Resend se agotó, la fila queda pendiente y el siguiente intento usa el MISMO cupón.
 * - Recuperación: cada ejecución revisa hoy y los 2 días anteriores (3 en total), por si
 *   el servidor estuvo caído; la clave única impide duplicar.
 * - 29 de febrero: en años no bisiestos se saluda el 28.
 * - Destinatarios: User::recibePromociones (correo con evidencia, activo, sin baja, sin
 *   rebote ni queja) con fecha de nacimiento. El envío pasa por EnvioPromocional (cupo).
 */
class CumpleanosService
{
    public const DIAS_DE_RECUPERACION = 3;

    /** Hasta cuántos días atrás se reintenta un pendiente o fallido. */
    public const DIAS_DE_REINTENTO = 30;

    public function __construct(private CouponService $cupones, private EnvioPromocional $envio) {}

    public static function zona(): string
    {
        return config('marketing.timezone', 'America/La_Paz');
    }

    /** Hoy en La Paz (a medianoche). */
    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona())->startOfDay();
    }

    /**
     * Fechas de nacimiento (mes, día) que se saludan en $dia. El 28 de febrero de un año no
     * bisiesto incluye a quienes nacieron el 29.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function nacimientosDelDia(CarbonImmutable $dia): array
    {
        $pares = [[$dia->month, $dia->day]];
        if ($dia->month === 2 && $dia->day === 28 && ! $dia->isLeapYear()) {
            $pares[] = [2, 29];
        }

        return $pares;
    }

    /** Clientes que pueden recibir promociones y cumplen años en $dia. */
    public function consultaCumpleaneros(CarbonImmutable $dia): Builder
    {
        return User::query()
            ->recibePromociones()
            ->whereNotNull('birth_date')
            ->where(function (Builder $q) use ($dia) {
                foreach (self::nacimientosDelDia($dia) as [$mes, $diaDelMes]) {
                    $q->orWhere(fn (Builder $o) => $o->whereMonth('birth_date', $mes)->whereDay('birth_date', $diaDelMes));
                }
            });
    }

    /** @return Collection<int, User> sin correos repetidos ni con formato inválido */
    public function cumpleanerosDe(CarbonImmutable $dia): Collection
    {
        return User::destinatariosDePromociones($this->consultaCumpleaneros($dia));
    }

    /**
     * Envía los correos de cumpleaños de $hoy (y recupera los 2 días anteriores).
     *
     * @return array<string, int> enviados, pospuestos, fallidos, omitidos, ya_enviados
     */
    public function enviar(?CarbonImmutable $hoy = null): array
    {
        $hoy ??= self::hoy();
        $ajustes = BirthdaySetting::actual();
        $resumen = ['enviados' => 0, 'pospuestos' => 0, 'fallidos' => 0, 'omitidos' => 0, 'ya_enviados' => 0];
        $procesadas = [];

        // 1. Pendientes y fallidos de días anteriores (cupo agotado o error de Resend).
        $reintentos = BirthdayEmail::query()
            ->whereIn('status', [BirthdayEmail::PENDIENTE, BirthdayEmail::FALLIDO])
            ->where('attempts', '<', BirthdayEmail::MAX_INTENTOS)
            ->whereDate('birthday_date', '>=', $hoy->subDays(self::DIAS_DE_REINTENTO)->toDateString())
            ->orderBy('birthday_date')
            ->get();
        foreach ($reintentos as $fila) {
            $procesadas[$fila->id] = true;
            $this->procesar($fila, $hoy, $ajustes, $resumen);
        }

        // 2. Cumpleaños de hoy y de los 2 días anteriores que aún no tengan fila ese año.
        for ($atras = 0; $atras < self::DIAS_DE_RECUPERACION; $atras++) {
            $dia = $hoy->subDays($atras);
            foreach ($this->cumpleanerosDe($dia) as $user) {
                $fila = $this->filaDelAnio($user, $dia, $hoy, $ajustes);
                if (isset($procesadas[$fila->id])) {
                    continue;
                }
                $procesadas[$fila->id] = true;
                $this->procesar($fila, $hoy, $ajustes, $resumen);
            }
        }

        return $resumen;
    }

    /**
     * Vencimiento del cupón: fin del día en La Paz, $dias después de $hoy. Se devuelve en
     * la zona de la app (UTC): Eloquent guarda la hora tal cual, sin convertirla.
     */
    public static function vencimiento(CarbonImmutable $hoy, int $dias): CarbonImmutable
    {
        return $hoy->setTimezone(self::zona())->addDays($dias)->endOfDay()->setTimezone(config('app.timezone'));
    }

    /**
     * La fila del año para este cliente; si no existe, la crea junto con su cupón en una
     * transacción. Si otra ejecución la creó en paralelo, se usa esa (y este cupón se
     * deshace con la transacción).
     */
    private function filaDelAnio(User $user, CarbonImmutable $dia, CarbonImmutable $hoy, BirthdaySetting $ajustes): BirthdayEmail
    {
        $existente = BirthdayEmail::where('user_id', $user->id)->where('year', $dia->year)->first();
        if ($existente) {
            return $existente;
        }

        try {
            return DB::transaction(function () use ($user, $dia, $hoy, $ajustes) {
                $cupon = $this->cupones->generateBirthdayCoupon(
                    $user->id,
                    (float) $ajustes->discount_percent,
                    self::vencimiento($hoy, $ajustes->valid_days),
                );

                return BirthdayEmail::create([
                    'user_id' => $user->id,
                    'year' => $dia->year,
                    'birthday_date' => $dia->toDateString(),
                    'coupon_id' => $cupon->id,
                    'status' => BirthdayEmail::PENDIENTE,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return BirthdayEmail::where('user_id', $user->id)->where('year', $dia->year)->firstOrFail();
        }
    }

    private function procesar(BirthdayEmail $fila, CarbonImmutable $hoy, BirthdaySetting $ajustes, array &$resumen): void
    {
        if (in_array($fila->status, [BirthdayEmail::ENVIADO, BirthdayEmail::OMITIDO], true)) {
            $resumen['ya_enviados']++;

            return;
        }

        $user = $fila->user;
        $cupon = $fila->coupon;

        // Sin cupón (lo borraron a mano): se crea otro una sola vez y queda en la fila.
        if (! $cupon) {
            $cupon = $this->cupones->generateBirthdayCoupon($user->id, (float) $ajustes->discount_percent, self::vencimiento($hoy, $ajustes->valid_days));
            $fila->update(['coupon_id' => $cupon->id]);
        }

        // Si sale días después (cupo agotado, servidor caído), el plazo cuenta desde hoy:
        // mismo código, vencimiento corrido. Nunca se acorta.
        $vence = self::vencimiento($hoy, $ajustes->valid_days);
        if ($cupon->used_count === 0 && ($cupon->expires_at === null || $cupon->expires_at->lt($vence))) {
            $cupon->update(['expires_at' => $vence, 'end_date' => $vence]);
        }

        $resultado = $this->envio->enviar(new CorreoCumpleanos($user, $cupon->code, (float) $cupon->value, $cupon->expires_at, $fila->year));

        match ($resultado['estado']) {
            'enviado' => $fila->update(['status' => BirthdayEmail::ENVIADO, 'sent_at' => now(), 'last_error' => null]),
            // No se le envió (baja, rebote, sin evidencia...): no se reintenta y el cupón,
            // que nunca conoció, se desactiva.
            'omitido' => DB::transaction(function () use ($fila, $cupon, $resultado) {
                $fila->update(['status' => BirthdayEmail::OMITIDO, 'last_error' => $resultado['motivo']]);
                $cupon->update(['is_active' => false]);
            }),
            // Cupo de Resend agotado: queda pendiente, sin gastar intento.
            'pospuesto' => $fila->update(['last_error' => $resultado['motivo']]),
            default => $fila->update([
                'status' => BirthdayEmail::FALLIDO,
                'attempts' => $fila->attempts + 1,
                'last_error' => $resultado['motivo'],
            ]),
        };

        $clave = match ($resultado['estado']) {
            'enviado' => 'enviados',
            'omitido' => 'omitidos',
            'pospuesto' => 'pospuestos',
            default => 'fallidos',
        };
        $resumen[$clave]++;
    }
}
