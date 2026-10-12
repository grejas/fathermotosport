<?php

namespace App\Support;

/**
 * "Inicio rápido" (faststart) de un MP4/MOV: mueve el índice del video (la caja moov)
 * delante de los datos (mdat), sin recodificar nada.
 *
 * Muchos programas y celulares guardan el moov al final del archivo. El navegador lo
 * necesita para empezar, así que tiene que bajar el archivo entero (si el servidor no
 * acepta rangos, como `php artisan serve`) o hacer una petición extra al final (si los
 * acepta, como LiteSpeed). Con el moov adelante, el video arranca con los primeros KB.
 *
 * Es el mismo procedimiento que `qt-faststart` / `ffmpeg -movflags +faststart`: se
 * reordenan las cajas de primer nivel y se corrigen los desplazamientos de los chunks
 * (stco/co64), que apuntan a posiciones absolutas dentro del archivo.
 */
class VideoInicioRapido
{
    /** Cajas que contienen otras cajas, en el camino hacia stco/co64. */
    private const CONTENEDORES = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts', 'dinf', 'udta'];

    /** Un moov más grande que esto no es un video normal: no se toca. */
    private const MAX_MOOV = 64 * 1024 * 1024;

    /**
     * Reordena el archivo en el lugar. Devuelve true si lo cambió; false si no hacía
     * falta (ya estaba bien, no es un MP4/MOV, o es fragmentado) o no se pudo.
     */
    public static function aplicar(string $ruta): bool
    {
        $f = @fopen($ruta, 'rb');
        if (! $f) {
            return false;
        }

        try {
            $total = filesize($ruta);
            $cajas = self::cajas($f, 0, $total);
            $tipos = array_column($cajas, 'tipo');

            // Solo ISO BMFF (MP4, MOV, M4V) con un moov y datos; los fragmentados (moof)
            // ya arrancan solos.
            if (! $cajas || ! in_array('moov', $tipos, true) || ! in_array('mdat', $tipos, true) || in_array('moof', $tipos, true)) {
                return false;
            }
            if (count(array_keys($tipos, 'moov', true)) !== 1) {
                return false;
            }

            $iMoov = array_search('moov', $tipos, true);
            $iMdat = array_search('mdat', $tipos, true);
            if ($iMoov < $iMdat) {
                return false; // ya está adelante
            }

            $moov = $cajas[$iMoov];
            if ($moov['tam'] > self::MAX_MOOV) {
                return false;
            }
            fseek($f, $moov['pos']);
            $bytesMoov = fread($f, $moov['tam']);
            if (strlen($bytesMoov) !== $moov['tam']) {
                return false;
            }

            // Todo lo que está entre el lugar nuevo del moov (antes del primer mdat) y su
            // lugar viejo se corre moov.tam bytes; lo que estaba después, no se mueve.
            $desde = $cajas[$iMdat]['pos'];
            $hasta = $moov['pos'];
            $bytesMoov = self::corregirDesplazamientos($bytesMoov, $desde, $hasta, $moov['tam']);
            if ($bytesMoov === null) {
                return false;
            }

            $temporal = $ruta.'.inicio-rapido';
            $salida = fopen($temporal, 'wb');
            try {
                foreach ($cajas as $i => $caja) {
                    if ($i === $iMdat) {
                        fwrite($salida, $bytesMoov);
                    }
                    if ($i === $iMoov) {
                        continue;
                    }
                    fseek($f, $caja['pos']);
                    if (stream_copy_to_stream($f, $salida, $caja['tam']) !== $caja['tam']) {
                        throw new \RuntimeException('copia incompleta');
                    }
                }
            } finally {
                fclose($salida);
            }
        } catch (\Throwable) {
            isset($temporal) && @unlink($temporal);

            return false;
        } finally {
            fclose($f);
        }

        if (filesize($temporal) !== $total) {
            @unlink($temporal);

            return false;
        }

        if (! @rename($temporal, $ruta)) {
            @unlink($temporal);

            return false;
        }

        return true;
    }

    /** ¿Tiene el moov después de los datos? (es decir, ¿le sirve aplicar()?) */
    public static function necesita(string $ruta): bool
    {
        $f = @fopen($ruta, 'rb');
        if (! $f) {
            return false;
        }
        $tipos = array_column(self::cajas($f, 0, filesize($ruta)), 'tipo');
        fclose($f);

        $iMoov = array_search('moov', $tipos, true);
        $iMdat = array_search('mdat', $tipos, true);

        return $iMoov !== false && $iMdat !== false && $iMoov > $iMdat && ! in_array('moof', $tipos, true);
    }

    /**
     * Cajas entre $desde y $hasta (posición, tamaño total, tipo, inicio del contenido).
     * Devuelve [] si la estructura no es válida.
     *
     * @param  resource  $f
     * @return list<array{tipo: string, pos: int, tam: int, datos: int}>
     */
    private static function cajas($f, int $desde, int $hasta): array
    {
        $lista = [];
        for ($pos = $desde; $pos < $hasta;) {
            if ($hasta - $pos < 8) {
                return [];
            }
            fseek($f, $pos);
            $cab = fread($f, 8);
            if (strlen($cab) < 8) {
                return [];
            }
            $tam = unpack('N', $cab)[1];
            $tipo = substr($cab, 4, 4);
            $cabecera = 8;
            if ($tam === 1) {
                $tam = unpack('J', fread($f, 8))[1];
                $cabecera = 16;
            } elseif ($tam === 0) {
                $tam = $hasta - $pos;
            }
            if ($tam < $cabecera || $pos + $tam > $hasta || ! preg_match('/^[\x20-\x7e]{4}$/', $tipo)) {
                return [];
            }
            $lista[] = ['tipo' => $tipo, 'pos' => $pos, 'tam' => $tam, 'datos' => $pos + $cabecera];
            $pos += $tam;
        }

        return $lista;
    }

    /**
     * Suma $delta a cada desplazamiento de stco/co64 dentro del moov que apunte a
     * [$desde, $hasta). Devuelve null si un stco de 32 bits se desbordaría.
     */
    private static function corregirDesplazamientos(string $moov, int $desde, int $hasta, int $delta): ?string
    {
        $mem = fopen('php://memory', 'w+b');
        fwrite($mem, $moov);
        $ok = self::recorrer($mem, 0, strlen($moov), $desde, $hasta, $delta);
        rewind($mem);
        $resultado = stream_get_contents($mem);
        fclose($mem);

        return $ok ? $resultado : null;
    }

    /** @param resource $mem */
    private static function recorrer($mem, int $inicio, int $fin, int $desde, int $hasta, int $delta): bool
    {
        foreach (self::cajas($mem, $inicio, $fin) as $caja) {
            if (in_array($caja['tipo'], self::CONTENEDORES, true)) {
                if (! self::recorrer($mem, $caja['datos'], $caja['pos'] + $caja['tam'], $desde, $hasta, $delta)) {
                    return false;
                }

                continue;
            }
            if ($caja['tipo'] !== 'stco' && $caja['tipo'] !== 'co64') {
                continue;
            }

            $ancho = $caja['tipo'] === 'stco' ? 4 : 8;
            fseek($mem, $caja['datos'] + 4); // versión y flags
            $cantidad = unpack('N', fread($mem, 4))[1];
            if ($caja['datos'] + 8 + $cantidad * $ancho > $caja['pos'] + $caja['tam']) {
                return false;
            }
            $tabla = fread($mem, $cantidad * $ancho);
            $valores = $cantidad ? array_values(unpack($ancho === 4 ? 'N*' : 'J*', $tabla)) : [];
            foreach ($valores as &$v) {
                if ($v >= $desde && $v < $hasta) {
                    $v += $delta;
                }
                if ($ancho === 4 && $v > 0xFFFFFFFF) {
                    return false;
                }
            }
            unset($v);
            fseek($mem, $caja['datos'] + 8);
            fwrite($mem, $cantidad ? pack($ancho === 4 ? 'N*' : 'J*', ...$valores) : '');
        }

        return true;
    }
}
