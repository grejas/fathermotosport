<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos del disco public que se suben desde el panel (banners, galería de productos):
 * borrarlos cuando nadie más los usa y preparar los videos para que arranquen rápido.
 */
class ArchivoPublico
{
    /** Una ruta del disco public (no vacía y no una URL externa: http…, data:…). */
    public static function esLocal(?string $ruta): bool
    {
        $ruta = trim((string) $ruta);

        return $ruta !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $ruta);
    }

    /**
     * Borra un archivo del disco public, salvo que sea una URL externa o que `$enUso`
     * diga que otro registro lo sigue usando (por ejemplo, uno duplicado a mano en la base).
     *
     * @param  callable(string): bool  $enUso
     */
    public static function borrarSiNoSeUsa(?string $ruta, callable $enUso): void
    {
        if (! self::esLocal($ruta)) {
            return;
        }

        $ruta = trim((string) $ruta);
        if (! $enUso($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }

    /**
     * Pone el índice del video al principio (ver VideoInicioRapido). Si no es un MP4/MOV,
     * ya estaba bien o algo falla, el archivo queda como estaba: nunca impide guardar.
     * `$de` es para el log ("banner", "producto").
     */
    public static function prepararVideo(?string $ruta, string $de): bool
    {
        if (! self::esLocal($ruta)) {
            return false;
        }

        $ruta = trim((string) $ruta);

        try {
            $disco = Storage::disk('public');
            if (! $disco->exists($ruta)) {
                return false;
            }

            $cambiado = VideoInicioRapido::aplicar($disco->path($ruta));
            if ($cambiado) {
                Log::info("Video de {$de} reordenado para inicio rápido (moov al principio).", ['ruta' => $ruta]);
            }

            return $cambiado;
        } catch (\Throwable $e) {
            Log::warning("No se pudo preparar el video del {$de} para inicio rápido.", ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
