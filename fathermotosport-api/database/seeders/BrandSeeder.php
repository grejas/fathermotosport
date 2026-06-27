<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'AGV', 'country' => 'Italia'],
            ['name' => 'Shoei', 'country' => 'Japón'],
            ['name' => 'Shark', 'country' => 'Francia'],
            ['name' => 'HJC', 'country' => 'Corea del Sur'],
            ['name' => 'Ruroc', 'country' => 'Reino Unido'],
            ['name' => 'Alpinestars', 'country' => 'Italia'],
            ['name' => 'Dainese', 'country' => 'Italia'],
            ['name' => 'Arai', 'country' => 'Japón'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($brand['name'])],
                [
                    'name' => $brand['name'],
                    'country' => $brand['country'],
                    'is_active' => true,
                ]
            );
        }
    }
}
