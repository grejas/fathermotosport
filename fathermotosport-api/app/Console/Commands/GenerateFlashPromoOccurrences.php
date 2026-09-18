<?php

namespace App\Console\Commands;

use App\Models\FlashPromo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateFlashPromoOccurrences extends Command
{
    // Se mantiene el nombre para no romper el scheduler/cron ya configurado.
    protected $signature = 'flash-promos:generate-next-occurrences';

    protected $description = 'Avanza cada promoción flash recurrente terminada a su ventana vigente (misma fila, UPDATE).';

    public function handle(): int
    {
        $now = Carbon::now();

        // Promos recurrentes, activas, cuya ventana ya terminó. Cada una se ACTUALIZA
        // en su misma fila (no se crean filas nuevas). is_active=false => se detiene
        // (respeta la desactivación manual).
        $promos = FlashPromo::query()
            ->where('is_recurring', true)
            ->where('is_active', true)
            ->where('ends_at', '<', $now)
            ->get();

        $updated = 0;

        foreach ($promos as $promo) {
            $durationSeconds = $promo->ends_at->getTimestamp() - $promo->starts_at->getTimestamp();
            $restSeconds = max(0, (int) ($promo->rest_minutes ?? 0)) * 60;
            $periodSeconds = $durationSeconds + $restSeconds; // ciclo completo: activa + descanso

            // Configuración inválida (duración 0): no se puede ciclar, se salta.
            if ($periodSeconds <= 0 || $durationSeconds <= 0) {
                continue;
            }

            $elapsed = $now->getTimestamp() - $promo->starts_at->getTimestamp();

            // Índice del ciclo actual. Saltamos directo a la ventana más cercana a AHORA
            // (la activa ahora, o la próxima futura) sin replicar los ciclos intermedios.
            $k = intdiv(max(0, $elapsed), $periodSeconds);

            // Si ya pasó la parte activa del ciclo k (estamos en su descanso), la ventana
            // correcta es la del ciclo siguiente.
            if ($elapsed > $k * $periodSeconds + $durationSeconds) {
                $k++;
            }

            $newStart = $promo->starts_at->copy()->addSeconds($k * $periodSeconds);
            $newEnd = $newStart->copy()->addSeconds($durationSeconds);

            // UPDATE de la misma fila: solo se corren las fechas a la ventana vigente.
            $promo->update([
                'starts_at' => $newStart,
                'ends_at' => $newEnd,
            ]);

            $updated++;
        }

        $this->info("Promociones flash recurrentes actualizadas: {$updated}.");

        return self::SUCCESS;
    }
}
