<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Galería de productos con imágenes, GIF y videos cortos mezclados.
 *
 * Aditiva: las filas existentes quedan como 'image' (las .gif como 'gif') y la ficha se
 * ve igual que antes. Hasta ahora la web ponía primero la imagen principal y después el
 * resto por sort_order; desde ahora el orden es solo sort_order (el que se arrastra en el
 * panel). Para que nada cambie a la vista, antes se renumera sort_order con el orden que
 * se mostraba: principal primero, después sort_order y, en empate, id (el orden en que
 * MySQL devuelve las filas de un producto).
 */
return new class extends Migration
{
    /**
     * Se puede volver a correr: en MySQL los cambios de esquema no son transaccionales y,
     * si algo falla a mitad de camino, las columnas ya agregadas quedan. Por eso cada
     * columna se agrega solo si falta, y la renumeración se hace una sola vez (antes de
     * que exista media_type), así no pisa un orden que ya se haya arrastrado en el panel.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('product_images', 'media_type')) {
            $this->renumerarComoSeMostraba();
        }

        $columnas = [
            'media_type' => fn (Blueprint $t) => $t->string('media_type', 10)->default('image')->after('url'),
            'poster_url' => fn (Blueprint $t) => $t->string('poster_url')->nullable()->after('thumbnail_url'),
            'with_sound' => fn (Blueprint $t) => $t->boolean('with_sound')->default(false)->after('is_primary'),
        ];

        foreach ($columnas as $columna => $agregar) {
            if (! Schema::hasColumn('product_images', $columna)) {
                Schema::table('product_images', $agregar);
            }
        }

        DB::table('product_images')
            ->where('media_type', 'image')
            ->whereRaw('LOWER(url) LIKE ?', ['%.gif'])
            ->update(['media_type' => 'gif']);
    }

    public function down(): void
    {
        // Un video no se puede mostrar como imagen: sus filas se quitan (los archivos
        // quedan en el disco). Los GIF vuelven a ser imágenes, como antes.
        if (Schema::hasColumn('product_images', 'media_type')) {
            DB::table('product_images')->where('media_type', 'video')->delete();
        }

        $nuevas = array_values(array_filter(
            ['media_type', 'poster_url', 'with_sound'],
            fn (string $c) => Schema::hasColumn('product_images', $c)
        ));

        if ($nuevas) {
            Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn($nuevas));
        }
    }

    private function renumerarComoSeMostraba(): void
    {
        $filas = DB::table('product_images')
            ->orderBy('product_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'product_id', 'sort_order']);

        DB::transaction(function () use ($filas) {
            foreach ($filas->groupBy('product_id') as $delProducto) {
                foreach ($delProducto->values() as $posicion => $fila) {
                    if ((int) $fila->sort_order !== $posicion) {
                        DB::table('product_images')->where('id', $fila->id)->update(['sort_order' => $posicion]);
                    }
                }
            }
        });
    }
};
