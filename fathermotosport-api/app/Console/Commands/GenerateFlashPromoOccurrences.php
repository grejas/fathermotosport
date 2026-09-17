<?php

namespace App\Console\Commands;

use App\Models\FlashPromo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateFlashPromoOccurrences extends Command
{
    protected $signature = 'flash-promos:generate-next-occurrences';

    protected $description = 'Genera la siguiente ocurrencia de cada promoción flash recurrente cuya cadena ya terminó.';

    public function handle(): int
    {
        $now = Carbon::now();

        // Todas las cadenas recurrentes con su ocurrencia más reciente y sus categorías.
        $groups = FlashPromo::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurring_group_id')
            ->with('categories:id')
            ->get()
            ->groupBy('recurring_group_id');

        $created = 0;

        foreach ($groups as $groupId => $occurrences) {
            // La ocurrencia más reciente de la cadena (mayor ends_at).
            $latest = $occurrences->sortByDesc('ends_at')->first();

            // Respeta la desactivación manual: si la última se apagó, la cadena se detiene.
            if (! $latest->is_active) {
                continue;
            }

            // Genera hacia adelante hasta tener una ocurrencia que aún no haya terminado
            // (self-healing si el scheduler estuvo caído un rato). Cap de seguridad.
            $cursor = $latest;
            $categoryIds = $latest->categories->pluck('id')->all();
            $guard = 0;

            while ($cursor->ends_at->lessThanOrEqualTo($now) && $guard < 500) {
                $durationSeconds = $cursor->ends_at->getTimestamp() - $cursor->starts_at->getTimestamp();
                $rest = (int) ($cursor->rest_minutes ?? 0);

                $newStart = $cursor->ends_at->copy()->addMinutes($rest);
                $newEnd = $newStart->copy()->addSeconds($durationSeconds);

                $next = FlashPromo::create([
                    'starts_at' => $newStart,
                    'ends_at' => $newEnd,
                    'promo_text' => $cursor->promo_text,
                    'is_active' => true,
                    'is_recurring' => true,
                    'rest_minutes' => $cursor->rest_minutes,
                    'recurring_group_id' => $groupId,
                ]);

                if (! empty($categoryIds)) {
                    $next->categories()->sync($categoryIds);
                }

                $created++;
                $guard++;
                $cursor = $next;
            }
        }

        $this->info("Ocurrencias de promoción flash generadas: {$created}.");

        return self::SUCCESS;
    }
}
