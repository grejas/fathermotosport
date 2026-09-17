<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FlashPromo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FlashPromoSeeder extends Seeder
{
    public function run(): void
    {
        $cascos = Category::where('slug', 'cascos')->first();

        $promo = FlashPromo::create([
            'starts_at' => Carbon::now(),
            'ends_at' => Carbon::now()->addHour(),
            'promo_text' => 'LLÉVATE UNA VISERA GRATIS POR LA COMPRA DE CUALQUIER CASCO',
            'is_active' => true,
        ]);

        if ($cascos) {
            $promo->categories()->sync([$cascos->id]);
        }
    }
}
