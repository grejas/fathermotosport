<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Support\VideoInicioRapido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pone el índice (moov) al principio en los videos de banners ya subidos. Los nuevos se
 * preparan solos al guardarlos (Banner::prepararVideo); esto es para los anteriores.
 */
class PrepararVideosDeBanners extends Command
{
    protected $signature = 'banners:preparar-videos
                            {--apply : Reordena los archivos (sin este flag solo muestra qué haría)}';

    protected $description = 'Reordena los MP4/MOV de banners que tienen el índice al final, para que el video arranque sin bajarlo entero.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY: se reordenarán los archivos.' : 'Modo DRY-RUN: no se modificará nada.');

        $disco = Storage::disk('public');
        $rutas = Banner::query()
            ->where('media_type', 'video')
            ->get(['video_path', 'video_mobile_path'])
            ->flatMap(fn (Banner $b) => [$b->video_path, $b->video_mobile_path])
            ->filter(fn (?string $r) => filled($r) && ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $r))
            ->unique()
            ->values();

        $aReordenar = 0;
        foreach ($rutas as $ruta) {
            if (! $disco->exists($ruta)) {
                $this->warn("  {$ruta}: no está en el disco");

                continue;
            }
            if (! VideoInicioRapido::necesita($disco->path($ruta))) {
                $this->line("  {$ruta}: ya arranca rápido o no es MP4/MOV");

                continue;
            }

            $aReordenar++;
            if (! $apply) {
                $this->line("  {$ruta}: tiene el índice al final");

                continue;
            }
            Banner::prepararVideo($ruta)
                ? $this->info("  {$ruta}: reordenado")
                : $this->error("  {$ruta}: no se pudo reordenar (quedó como estaba)");
        }

        $this->newLine();
        $this->line("Videos revisados: {$rutas->count()} · ".($apply ? 'reordenados' : 'a reordenar').": {$aReordenar}");
        if (! $apply && $aReordenar) {
            $this->comment('Para aplicarlo: php artisan banners:preparar-videos --apply');
        }

        return self::SUCCESS;
    }
}
