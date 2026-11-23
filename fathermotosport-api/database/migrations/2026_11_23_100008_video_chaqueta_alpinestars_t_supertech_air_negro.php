<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Pedido de Gerson (23-nov-2026): video de presentación (8 s, giro 360°, sin sonido)
 * para la Chaqueta Alpinestars T-Supertech Air Negro, primero en la galería.
 *
 * Copia el MP4 y su póster de database/seed-assets/videos al disco público y crea el
 * elemento de galería. La foto de portada del catálogo no cambia (sale de las imágenes).
 * Idempotente: si el producto ya tiene ese video, no hace nada.
 */
return new class extends Migration
{
    private const SLUG = 'chaqueta-alpinestars-t-supertech-air-negro';

    private const VIDEO = 'products/videos/supertech-air-negro.mp4';

    private const POSTER = 'products/videos/supertech-air-negro-poster.jpg';

    public function up(): void
    {
        $product = Product::withTrashed()->where('slug', self::SLUG)->first();
        if (! $product || $product->images()->where('url', self::VIDEO)->exists()) {
            return;
        }

        $disco = Storage::disk('public');
        $origen = database_path('seed-assets/videos');
        $disco->put(self::VIDEO, file_get_contents($origen.'/supertech-air-negro.mp4'));
        $disco->put(self::POSTER, file_get_contents($origen.'/supertech-air-negro-poster.jpg'));

        // Va primero: corre una posición todo lo demás.
        $product->images()->increment('sort_order');

        $product->images()->create([
            'url' => self::VIDEO,
            'poster_url' => self::POSTER,
            'sort_order' => 0,
            'is_primary' => false,
            'with_sound' => false,
        ]);
    }

    public function down(): void
    {
        // No se borra automáticamente: el video se quita desde el panel.
    }
};
