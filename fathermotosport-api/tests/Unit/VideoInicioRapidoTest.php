<?php

namespace Tests\Unit;

use App\Support\VideoInicioRapido;
use PHPUnit\Framework\TestCase;

/**
 * El reordenamiento de MP4: el moov pasa adelante y cada desplazamiento de chunk sigue
 * apuntando a los mismos datos. Los MP4 se arman a mano, caja por caja.
 */
class VideoInicioRapidoTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/inicio-rapido-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    private static function caja(string $tipo, string $contenido): string
    {
        return pack('N', 8 + strlen($contenido)).$tipo.$contenido;
    }

    /**
     * Un MP4 mínimo con dos pistas cuyos chunks apuntan a trozos de mdat. Cada chunk
     * empieza con una marca distinta ("CHUNK-0-..."), así se puede comprobar adónde apunta.
     *
     * @return array{0: string, 1: list<string>} [bytes del archivo, marcas en orden]
     */
    public static function mp4(bool $moovAlFinal = true, bool $co64 = false): array
    {
        $ftyp = self::caja('ftyp', 'isom'."\0\0\2\0".'isomiso2avc1mp41');
        $marcas = [];
        $datos = '';
        foreach (range(0, 5) as $i) {
            $marcas[] = sprintf('CHUNK-%d-%s', $i, str_repeat(chr(65 + $i), 20));
            $datos .= $marcas[$i].str_repeat("\0", 100);
        }
        $mdatSinCabecera = $datos;

        // Los desplazamientos dependen de dónde quede mdat: se arma el moov dos veces.
        $armarMoov = function (int $inicioDatos) use ($co64, $marcas) {
            $pistas = '';
            foreach ([[0, 2, 4], [1, 3, 5]] as $chunks) {
                $offsets = array_map(fn ($i) => $inicioDatos + $i * (strlen($marcas[0]) + 100), $chunks);
                $tabla = $co64
                    ? self::caja('co64', "\0\0\0\0".pack('N', count($offsets)).pack('J*', ...$offsets))
                    : self::caja('stco', "\0\0\0\0".pack('N', count($offsets)).pack('N*', ...$offsets));
                $stbl = self::caja('stbl', self::caja('stsd', "\0\0\0\0\0\0\0\0").$tabla);
                $pistas .= self::caja('trak', self::caja('tkhd', str_repeat("\0", 20)).self::caja('mdia', self::caja('minf', $stbl)));
            }

            return self::caja('moov', self::caja('mvhd', str_repeat("\0", 20)).$pistas);
        };

        $mdat = self::caja('mdat', $mdatSinCabecera);
        if ($moovAlFinal) {
            $archivo = $ftyp.$mdat.$armarMoov(strlen($ftyp) + 8);
        } else {
            $largoMoov = strlen($armarMoov(0));
            $archivo = $ftyp.$armarMoov(strlen($ftyp) + $largoMoov + 8).$mdat;
        }

        return [$archivo, $marcas];
    }

    /** Lee, en el orden del archivo, las marcas a las que apunta cada chunk. */
    private static function marcasApuntadas(string $archivo): array
    {
        $marcas = [];
        $leer = function (int $ini, int $fin) use (&$leer, &$marcas, $archivo) {
            for ($p = $ini; $p + 8 <= $fin;) {
                $tam = unpack('N', substr($archivo, $p, 4))[1];
                $tipo = substr($archivo, $p + 4, 4);
                if (in_array($tipo, ['moov', 'trak', 'mdia', 'minf', 'stbl'], true)) {
                    $leer($p + 8, $p + $tam);
                }
                if ($tipo === 'stco' || $tipo === 'co64') {
                    $n = unpack('N', substr($archivo, $p + 12, 4))[1];
                    $offs = unpack($tipo === 'stco' ? 'N*' : 'J*', substr($archivo, $p + 16, $n * ($tipo === 'stco' ? 4 : 8)));
                    foreach ($offs as $o) {
                        $marcas[] = substr($archivo, $o, 28);
                    }
                }
                $p += $tam;
            }
        };
        $leer(0, strlen($archivo));

        return $marcas;
    }

    private static function ordenDeCajas(string $archivo): array
    {
        $tipos = [];
        for ($p = 0; $p < strlen($archivo);) {
            $tipos[] = substr($archivo, $p + 4, 4);
            $p += unpack('N', substr($archivo, $p, 4))[1];
        }

        return $tipos;
    }

    public function test_moves_moov_before_mdat_and_keeps_every_chunk_pointing_to_its_data(): void
    {
        [$bytes, $marcas] = self::mp4();
        $ruta = $this->dir.'/video.mp4';
        file_put_contents($ruta, $bytes);
        $esperadas = self::marcasApuntadas($bytes);
        $this->assertSame([$marcas[0], $marcas[2], $marcas[4], $marcas[1], $marcas[3], $marcas[5]], $esperadas);

        $this->assertTrue(VideoInicioRapido::necesita($ruta));
        $this->assertTrue(VideoInicioRapido::aplicar($ruta));

        $resultado = file_get_contents($ruta);
        $this->assertSame(['ftyp', 'moov', 'mdat'], self::ordenDeCajas($resultado));
        $this->assertSame(strlen($bytes), strlen($resultado));
        $this->assertSame($esperadas, self::marcasApuntadas($resultado));
        $this->assertFalse(VideoInicioRapido::necesita($ruta));
        $this->assertFileDoesNotExist($ruta.'.inicio-rapido');
    }

    public function test_also_fixes_64_bit_offsets(): void
    {
        [$bytes] = self::mp4(co64: true);
        $ruta = $this->dir.'/video.mov';
        file_put_contents($ruta, $bytes);

        $this->assertTrue(VideoInicioRapido::aplicar($ruta));
        $this->assertSame(self::marcasApuntadas($bytes), self::marcasApuntadas(file_get_contents($ruta)));
    }

    public function test_leaves_alone_a_file_that_already_starts_fast(): void
    {
        [$bytes] = self::mp4(moovAlFinal: false);
        $ruta = $this->dir.'/video.mp4';
        file_put_contents($ruta, $bytes);

        $this->assertFalse(VideoInicioRapido::necesita($ruta));
        $this->assertFalse(VideoInicioRapido::aplicar($ruta));
        $this->assertSame($bytes, file_get_contents($ruta));
    }

    public function test_leaves_alone_files_that_are_not_mp4_or_are_broken(): void
    {
        $casos = [
            'video.webm' => hex2bin('1A45DFA39F4286810142F7810142F2810442F381084282847765626D').str_repeat("\0", 500),
            'video.avi' => 'RIFF'.pack('V', 500).'AVI LIST'.str_repeat("\0", 500),
            'cortado.mp4' => substr(self::mp4()[0], 0, -30),
            'vacio.mp4' => '',
        ];

        foreach ($casos as $nombre => $bytes) {
            $ruta = $this->dir.'/'.$nombre;
            file_put_contents($ruta, $bytes);
            $this->assertFalse(VideoInicioRapido::aplicar($ruta), $nombre);
            $this->assertSame($bytes, file_get_contents($ruta), $nombre);
        }
    }
}
