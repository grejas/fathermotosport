<?php

namespace App\Console\Commands;

use App\Models\BirthdayEmail;
use App\Models\User;
use App\Services\CumpleanosService;
use App\Support\BajaDePromociones;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Solo lectura: quiénes recibirían el correo de cumpleaños un día y cuántos quedan fuera
 * (y por qué). No envía nada ni crea cupones; los correos salen ocultos.
 */
class SimularCumpleanos extends Command
{
    protected $signature = 'cumpleanos:simular {fecha : AAAA-MM-DD (dia en La Paz)}';

    protected $description = 'Lista (sin enviar) quiénes recibirían el correo de cumpleaños ese día.';

    public function handle(CumpleanosService $cumpleanos): int
    {
        $dia = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->argument('fecha'), CumpleanosService::zona());
        if (! $dia || $dia->format('Y-m-d') !== $this->argument('fecha')) {
            $this->error('Fecha inválida: usa AAAA-MM-DD.');

            return self::INVALID;
        }

        $reciben = $cumpleanos->cumpleanerosDe($dia);
        $yaEnviados = BirthdayEmail::where('year', $dia->year)->whereIn('user_id', $reciben->pluck('id'))
            ->whereIn('status', [BirthdayEmail::ENVIADO, BirthdayEmail::OMITIDO])->pluck('user_id')->flip();

        $this->info("Cumpleaños del {$dia->toDateString()}: ".$reciben->count().' recibirían el correo ('.$yaEnviados->count().' ya lo recibieron este año).');
        if ($reciben->isNotEmpty()) {
            $this->table(['cliente', 'nombre', 'correo', 'idioma', 'este año'], $reciben->map(fn (User $u) => [
                substr($u->id, 0, 8),
                trim($u->first_name.' '.mb_substr((string) $u->last_name, 0, 1)),
                BajaDePromociones::correoOculto($u->email),
                $u->locale ?: 'es',
                isset($yaEnviados[$u->id]) ? 'ya enviado' : 'pendiente',
            ])->all());
        }

        // Clientes que cumplen ese día pero no lo recibirían, agrupados por motivo.
        $todos = User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->whereNotNull('birth_date')
            ->where(function (Builder $q) use ($dia) {
                foreach (CumpleanosService::nacimientosDelDia($dia) as [$mes, $diaDelMes]) {
                    $q->orWhere(fn (Builder $o) => $o->whereMonth('birth_date', $mes)->whereDay('birth_date', $diaDelMes));
                }
            })
            ->get();
        $idsQueReciben = $reciben->pluck('id')->flip();
        $motivos = $todos->reject(fn (User $u) => isset($idsQueReciben[$u->id]))
            ->map(fn (User $u) => $u->motivoSinPromociones() ?? 'correo repetido')
            ->countBy();

        $this->line('Excluidos: '.$motivos->sum().($motivos->isEmpty() ? '' : ' ('.$motivos->map(fn ($n, $m) => "$m: $n")->implode(', ').')'));
        $this->line('De ellos, por falta de evidencia de verificación: '.($motivos['correo sin evidencia de verificación'] ?? 0));

        return self::SUCCESS;
    }
}
