<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $letras = ['size_type' => Category::SIZE_LETTERS, 'size_options' => ['sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']]];

        $categories = [
            ['name' => 'Cascos', 'icon' => 'helmet', 'tallas' => $letras],
            ['name' => 'Guantes', 'icon' => 'gloves', 'tallas' => $letras],
            ['name' => 'Botas', 'icon' => 'boots', 'tallas' => ['size_type' => Category::SIZE_NUMERIC, 'size_options' => ['from' => 36, 'to' => 47, 'step' => 1]]],
            ['name' => 'Viseras', 'icon' => 'visor'],
            ['name' => 'Chamarras', 'icon' => 'jacket', 'tallas' => $letras],
            ['name' => 'Repuestos', 'icon' => 'parts'],
            ['name' => 'Accesorios', 'icon' => 'accessories'],
        ];

        foreach ($categories as $index => $category) {
            $registro = Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );

            // El tipo de stock solo se pone al crearla: volver a correr el seeder no pisa
            // lo que se haya configurado en el panel.
            if ($registro->wasRecentlyCreated && isset($category['tallas'])) {
                $registro->update($category['tallas']);
            }
        }
    }
}
