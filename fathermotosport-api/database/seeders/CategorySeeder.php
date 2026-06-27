<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Cascos', 'icon' => 'helmet'],
            ['name' => 'Guantes', 'icon' => 'gloves'],
            ['name' => 'Botas', 'icon' => 'boots'],
            ['name' => 'Viseras', 'icon' => 'visor'],
            ['name' => 'Chamarras', 'icon' => 'jacket'],
            ['name' => 'Repuestos', 'icon' => 'parts'],
            ['name' => 'Accesorios', 'icon' => 'accessories'],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
