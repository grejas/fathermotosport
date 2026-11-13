<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga las sudaderas "Heubow Divinity Arcángel 11-19" copiando todo de la plantilla
 * (Arcángel 10): categoría, marca, precio, costo, descripción, tallas, stock y estado.
 * Solo cambia el nombre, el color, el SKU y la galería.
 *
 * Además agrega la foto de detalle del tirador con cruz al final de la galería de las
 * sudaderas cuya foto principal muestra el cierre (nuevas y Arcángel 02-10 ya
 * publicadas), y cambia la foto principal de las Arcángel 02-10 por su versión
 * encuadrada (cuadrada 1200×1200, como las nuevas).
 *
 * Idempotente: un producto que ya existe (por slug) o una galería que ya tiene la foto
 * del cierre se saltan.
 */
class ImportarHoodiesHeubow extends Command
{
    protected $signature = 'products:import-heubow
                            {--apply : Aplica los cambios (sin este flag solo muestra qué haría)}
                            {--template=sudadera-con-capucha-divinity-arcangel-10 : Slug del producto plantilla}';

    protected $description = 'Crea las sudaderas Heubow Divinity Arcángel 11-19 a partir de la plantilla y agrega la foto del cierre con cruz.';

    private const ASSETS = 'database/seed-assets/heubow';

    private const FOTO_CIERRE = 'detalle-cierre-cruz.jpg';

    /** número => [color, cantidad de fotos, tiene cierre] */
    private const NUEVAS = [
        11 => ['Negro y naranja', 2, true],
        12 => ['Negro y rojo', 2, true],
        13 => ['Negro y azul', 2, true],
        14 => ['Negro y violeta', 1, false], // su foto principal es la espalda: no se ve el cierre
        15 => ['Negro y violeta', 1, false], // ídem
        16 => ['Negro y blanco', 1, true],
        17 => ['Negro y gris', 1, true],
        18 => ['Negro y rojo', 1, true],
        19 => ['Negro y blanco', 1, false], // canguro cerrado, sin cierre
    ];

    /** Sudaderas ya publicadas cuya foto principal muestra el cierre (en la 01 no se ve). */
    private const EXISTENTES_CON_CIERRE = ['02', '03', '04', '05', '06', '07', '08', '09', '10'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY: se aplicarán los cambios.' : 'Modo DRY-RUN: no se modificará nada.');

        $plantilla = Product::with('variants')->where('slug', $this->option('template'))->first();
        if (! $plantilla) {
            $this->error('No encontré la plantilla '.$this->option('template').'.');

            return self::FAILURE;
        }

        $faltantes = collect(array_keys(self::NUEVAS))
            ->flatMap(fn ($n) => collect(range(1, self::NUEVAS[$n][1]))->map(fn ($i) => "{$n}-{$i}.jpg"))
            ->push(self::FOTO_CIERRE)
            ->merge(collect(self::EXISTENTES_CON_CIERRE)->map(fn ($n) => "existente-{$n}.jpg"))
            ->reject(fn ($f) => is_file(base_path(self::ASSETS.'/'.$f)));
        if ($faltantes->isNotEmpty()) {
            $this->error('Faltan fotos en '.self::ASSETS.': '.$faltantes->implode(', '));

            return self::FAILURE;
        }

        $tallas = $plantilla->variants->sortBy(fn ($v) => array_search($v->size, ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL']))->values();
        $this->line("Plantilla: {$plantilla->name} · {$plantilla->price} · tallas ".$tallas->map(fn ($v) => "{$v->size}:{$v->stock}")->implode(' '));

        $filas = [];
        $creados = 0;

        foreach (self::NUEVAS as $n => [$color, $fotos, $conCierre]) {
            $nombre = "Sudadera con Capucha Heubow Divinity Arcángel {$n}";
            $slug = Str::slug($nombre);
            $existe = Product::withTrashed()->where('slug', $slug)->exists();

            $filas[] = [$n, $nombre, $color, $fotos + ($conCierre ? 1 : 0), $conCierre ? 'sí' : 'no', $existe ? 'YA EXISTE (se salta)' : 'nuevo'];

            if (! $apply || $existe) {
                continue;
            }

            DB::transaction(function () use ($plantilla, $tallas, $n, $nombre, $slug, $color, $fotos, $conCierre) {
                $producto = $plantilla->replicate(['sku', 'slug', 'name', 'color', 'description', 'spin_url']);
                $producto->name = $nombre;
                $producto->slug = $slug;
                $producto->color = $color;
                $producto->sku = $this->skuUnico();
                $producto->description = $this->descripcion($plantilla, $nombre, $color);
                $producto->save();

                foreach ($tallas as $v) {
                    $producto->variants()->create([
                        'size' => $v->size,
                        'finish' => $v->finish,
                        'sku' => ProductVariant::skuFor($producto->sku, $v->size),
                        'stock' => $v->stock,
                        'is_active' => $v->is_active,
                    ]);
                }

                $orden = 1;
                foreach (range(1, $fotos) as $i) {
                    $this->agregarFoto($producto, "{$n}-{$i}.jpg", "heubow_arcangel_{$n}_{$i}_encuadre", $orden++);
                }
                if ($conCierre) {
                    $this->agregarFoto($producto, self::FOTO_CIERRE, 'detalle_cierre_cruz', $orden);
                }
            });
            $creados++;
        }

        $this->table(['N°', 'Nombre', 'Color', 'Fotos', 'Cierre', 'Estado'], $filas);

        // Foto del cierre en las Arcángel ya publicadas.
        $existentes = [];
        foreach (self::EXISTENTES_CON_CIERRE as $n) {
            $p = Product::with('images')->where('slug', "sudadera-con-capucha-divinity-arcangel-{$n}")->first();
            if (! $p) {
                $existentes[] = [$n, '—', 'no existe'];

                continue;
            }
            $tiene = $p->images->contains(fn ($img) => str_contains((string) $img->url, 'detalle_cierre_cruz'));
            $principal = $p->images->firstWhere('is_primary', true) ?? $p->images->sortBy('sort_order')->first();
            $encuadrada = $principal && str_contains((string) $principal->url, '_encuadre');

            $existentes[] = [
                $n,
                $p->name,
                $encuadrada ? 'ya encuadrada' : ($principal ? 'se reemplaza por la encuadrada' : 'sin foto principal'),
                $tiene ? 'ya tiene la foto' : 'se agrega al final',
            ];

            if (! $apply) {
                continue;
            }

            if ($principal && ! $encuadrada) {
                // El hook "updated" de ProductImage borra del disco el archivo anterior.
                $principal->update(['url' => $this->guardarArchivo("existente-{$n}.jpg", "arcangel_{$n}_encuadre")]);
            }
            if (! $tiene) {
                $this->agregarFoto($p, self::FOTO_CIERRE, 'detalle_cierre_cruz', ((int) $p->images->max('sort_order')) + 1);
            }
        }
        $this->table(['N°', 'Producto existente', 'Foto principal', 'Foto del cierre'], $existentes);

        $this->info($apply ? "Listo: {$creados} sudaderas creadas." : 'Para aplicar: php artisan products:import-heubow --apply');

        return self::SUCCESS;
    }

    /** La descripción de la plantilla con el nombre y el color de la nueva. */
    private function descripcion(Product $plantilla, string $nombre, string $color): string
    {
        $base = (string) $plantilla->description;
        $nombreBase = Str::after($plantilla->name, 'Sudadera con Capucha '); // "Divinity Arcángel 10"
        $nombreNuevo = Str::after($nombre, 'Sudadera con Capucha ');      // "Heubow Divinity Arcángel 11"

        return str_replace(
            [$nombreBase, $plantilla->color, Str::lower($plantilla->color)],
            [$nombreNuevo, $color, Str::lower($color)],
            $base,
        );
    }

    private function agregarFoto(Product $producto, string $archivo, string $nombre, int $orden): void
    {
        $producto->images()->create([
            'url' => $this->guardarArchivo($archivo, $nombre),
            'sort_order' => $orden,
            'is_primary' => $orden === 1,
        ]);
    }

    /** Copia una foto de seed-assets al disco public y devuelve su ruta (products/...). */
    private function guardarArchivo(string $archivo, string $nombre): string
    {
        $destino = 'products/'.uniqid().'_'.$nombre.'.jpg';
        Storage::disk('public')->put($destino, file_get_contents(base_path(self::ASSETS.'/'.$archivo)));

        return $destino;
    }

    private function skuUnico(): string
    {
        do {
            $sku = 'FMS-BREATHEDIVINITY-'.Str::upper(Str::random(6));
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
