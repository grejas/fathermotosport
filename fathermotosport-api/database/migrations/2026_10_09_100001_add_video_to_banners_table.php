<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banners con video subido desde el panel, un texto opcional y una posición nueva
 * debajo de "Explora por categoría".
 *
 * Aditiva: los banners existentes quedan como imagen (media_type = 'image') y se ven
 * igual que antes. image_url pasa a nullable porque un banner de video no tiene
 * imagen: su portada es poster_path. 'sidebar' sigue en el ENUM para no invalidar
 * filas viejas, aunque el panel ya no la ofrece.
 */
return new class extends Migration
{
    private const POSICIONES = ['hero', 'sidebar', 'footer', 'home_after_categories'];

    private const POSICIONES_ANTES = ['hero', 'sidebar', 'footer'];

    /**
     * Se puede volver a correr: en MySQL los cambios de esquema no son transaccionales y,
     * si algo falla a mitad de camino, las columnas ya agregadas quedan. Por eso cada
     * columna se agrega solo si falta, y los change() dejan igual lo que ya está bien.
     */
    public function up(): void
    {
        $columnas = [
            'subtitle' => fn (Blueprint $t) => $t->text('subtitle')->nullable()->after('title'),
            'media_type' => fn (Blueprint $t) => $t->string('media_type', 10)->default('image')->after('image_url'),
            'video_path' => fn (Blueprint $t) => $t->string('video_path')->nullable()->after('media_type'),
            'video_mobile_path' => fn (Blueprint $t) => $t->string('video_mobile_path')->nullable()->after('video_path'),
            'poster_path' => fn (Blueprint $t) => $t->string('poster_path')->nullable()->after('video_mobile_path'),
            'video_with_sound' => fn (Blueprint $t) => $t->boolean('video_with_sound')->default(false)->after('poster_path'),
        ];

        foreach ($columnas as $columna => $agregar) {
            if (! Schema::hasColumn('banners', $columna)) {
                Schema::table('banners', $agregar);
            }
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
            $table->enum('position', self::POSICIONES)->default('hero')->change();
        });
    }

    public function down(): void
    {
        // Lo que no cabe en el esquema anterior: la posición nueva se borra, y los
        // banners de video conservan su portada como imagen (image_url es NOT NULL).
        DB::table('banners')->where('position', 'home_after_categories')->delete();
        DB::table('banners')->whereNull('image_url')->update(['image_url' => DB::raw('poster_path')]);
        DB::table('banners')->whereNull('image_url')->delete();

        $nuevas = array_values(array_filter(
            ['subtitle', 'media_type', 'video_path', 'video_mobile_path', 'poster_path', 'video_with_sound'],
            fn (string $c) => Schema::hasColumn('banners', $c)
        ));

        Schema::table('banners', function (Blueprint $table) use ($nuevas) {
            $table->enum('position', self::POSICIONES_ANTES)->default('hero')->change();
            if ($nuevas) {
                $table->dropColumn($nuevas);
            }
            $table->string('image_url')->nullable(false)->change();
        });
    }
};
