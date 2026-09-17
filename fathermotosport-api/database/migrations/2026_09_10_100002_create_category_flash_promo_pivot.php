<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte la relación promo→categoría de singular (category_id) a muchos-a-muchos:
 * una promoción puede aplicar a varias categorías a la vez. Sin categorías en el
 * pivote => la promo aplica a toda la tienda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_flash_promo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_promo_id')->constrained('flash_promos')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['flash_promo_id', 'category_id']);
        });

        // Migra el category_id existente (si lo hay) al pivote antes de eliminarlo.
        if (Schema::hasColumn('flash_promos', 'category_id')) {
            $rows = DB::table('flash_promos')->whereNotNull('category_id')->get(['id', 'category_id']);
            foreach ($rows as $row) {
                DB::table('category_flash_promo')->insert([
                    'flash_promo_id' => $row->id,
                    'category_id' => $row->category_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('flash_promos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('flash_promos', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('promo_text')->constrained('categories')->nullOnDelete();
        });

        // Restaura la primera categoría del pivote como category_id singular.
        foreach (DB::table('category_flash_promo')->get() as $row) {
            DB::table('flash_promos')
                ->where('id', $row->flash_promo_id)
                ->whereNull('category_id')
                ->update(['category_id' => $row->category_id]);
        }

        Schema::dropIfExists('category_flash_promo');
    }
};
