<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Comprime los videos del storage público (productos y banners) para la web: H.264 a
 * 720 p como máximo, ~1,5 Mbps, audio AAC y "faststart" (empiezan a reproducirse antes
 * de bajarse enteros). Los videos de WhatsApp y de celular pesaban 15–25 MB cada uno
 * y se descargaban enteros al abrir la ficha.
 *
 * - Reemplaza el archivo con el mismo nombre (la URL no cambia) y guarda el original
 *   fuera de lo público, en storage/app/originales/.
 * - Si al video le falta póster, saca uno del primer segundo: la galería muestra la foto
 *   al instante en vez de un recuadro negro.
 * - Lleva un registro de los ya procesados: correrlo de nuevo solo toma los nuevos.
 *
 * Necesita ffmpeg (FFMPEG_PATH; en Hostinger, el binario estático en ~/bin/ffmpeg).
 */
class OptimizarVideos extends Command
{
    protected $signature = 'media:optimizar-videos {--limite=5 : Cuántos videos procesar en esta corrida}';

    protected $description = 'Comprime los videos del storage público y les genera póster.';

    private const CARPETAS = ['products/videos', 'banners/videos'];

    /** Debajo de esto ya es liviano: solo se registra. */
    private const MINIMO_BYTES = 3 * 1024 * 1024;

    public function handle(): int
    {
        $ffmpeg = (string) config('services.ffmpeg.path');

        if ($ffmpeg === '' || ! is_executable($ffmpeg)) {
            $this->warn("No hay ffmpeg en «{$ffmpeg}»: no se procesa nada.");

            return self::SUCCESS;
        }

        $base = storage_path('app/public');
        $registro = storage_path('app/videos-optimizados.json');
        $hechos = is_file($registro) ? (json_decode((string) file_get_contents($registro), true) ?: []) : [];
        $limite = max(1, (int) $this->option('limite'));
        $procesados = 0;

        foreach (self::CARPETAS as $carpeta) {
            foreach (glob("{$base}/{$carpeta}/*.{mp4,MP4,mov,MOV,m4v}", GLOB_BRACE) ?: [] as $archivo) {
                $relativo = substr($archivo, strlen($base) + 1);
                $huella = filesize($archivo).'-'.filemtime($archivo);

                if (($hechos[$relativo] ?? null) === $huella) {
                    continue;
                }

                if ($procesados >= $limite) {
                    break 2;
                }

                $antes = filesize($archivo);

                // Si ffmpeg falla no se registra: se reintenta en la próxima corrida.
                if ($antes > self::MINIMO_BYTES && ! $this->comprimir($ffmpeg, $archivo, $relativo)) {
                    $procesados++;

                    continue;
                }

                $this->poster($ffmpeg, $archivo, $relativo);

                clearstatcache(true, $archivo);
                $hechos[$relativo] = filesize($archivo).'-'.filemtime($archivo);
                file_put_contents($registro, json_encode($hechos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                $this->line(sprintf('%s: %.1f MB → %.1f MB', $relativo, $antes / 1048576, filesize($archivo) / 1048576));
                $procesados++;
            }
        }

        $this->info("Listo: {$procesados} video(s).");

        return self::SUCCESS;
    }

    /** true si quedó resuelto (comprimido o no valía la pena); false si ffmpeg falló. */
    private function comprimir(string $ffmpeg, string $archivo, string $relativo): bool
    {
        $salida = $archivo.'.opt.mp4';

        $proceso = new Process([
            $ffmpeg, '-y', '-hide_banner', '-loglevel', 'error', '-i', $archivo,
            // El lado corto a 720 px como máximo (sirve para verticales y horizontales).
            '-vf', "scale='if(gt(iw,ih),-2,min(720,iw))':'if(gt(iw,ih),min(720,ih),-2)'",
            // Pocos hilos: el hosting compartido limita los procesos y x264, con todos los
            // núcleos, no llega a abrir el codificador.
            '-threads', '2', '-filter_threads', '1',
            '-c:v', 'libx264', '-x264-params', 'threads=2', '-preset', 'veryfast', '-crf', '28', '-maxrate', '1800k', '-bufsize', '3600k',
            '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', $salida,
        ]);
        $proceso->setTimeout(900);
        $proceso->run();

        if (! $proceso->isSuccessful() || ! is_file($salida) || filesize($salida) < 10240) {
            @unlink($salida);
            $this->warn("{$relativo}: ffmpeg falló, se deja el original. ".mb_substr(trim($proceso->getErrorOutput()), 0, 300));

            return false;
        }

        // Solo vale la pena si baja al menos un 20 %.
        if (filesize($salida) > filesize($archivo) * 0.8) {
            @unlink($salida);

            return true;
        }

        $respaldo = storage_path('app/originales/'.$relativo);
        File::ensureDirectoryExists(dirname($respaldo));
        copy($archivo, $respaldo);
        rename($salida, $archivo);

        return true;
    }

    /** Póster JPG del primer segundo para los videos que no tienen uno. */
    private function poster(string $ffmpeg, string $archivo, string $relativo): void
    {
        $imagenes = ProductImage::where('url', $relativo)->whereNull('poster_url')->get();
        $banners = Banner::where(fn ($q) => $q->where('video_path', $relativo)->orWhere('video_mobile_path', $relativo))
            ->whereNull('poster_path')->get();

        if ($imagenes->isEmpty() && $banners->isEmpty()) {
            return;
        }

        $poster = dirname($relativo).'/posters/'.pathinfo($relativo, PATHINFO_FILENAME).'.jpg';
        $destino = storage_path('app/public/'.$poster);
        File::ensureDirectoryExists(dirname($destino));

        $proceso = new Process([$ffmpeg, '-y', '-hide_banner', '-loglevel', 'error', '-threads', '1', '-ss', '0.5', '-i', $archivo,
            '-frames:v', '1', '-vf', "scale='min(1080,iw)':-2", '-q:v', '4', $destino]);
        $proceso->setTimeout(120);
        $proceso->run();

        if (! is_file($destino)) {
            return;
        }

        $imagenes->each(fn (ProductImage $img) => $img->update(['poster_url' => $poster]));
        $banners->each(fn (Banner $b) => $b->update(['poster_path' => $poster]));
    }
}
