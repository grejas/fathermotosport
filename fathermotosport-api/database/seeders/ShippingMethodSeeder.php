<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Envío Bolivia',
                'price' => 0,
                'country' => 'Bolivia',
                'delivery_days_min' => 2,
                'delivery_days_max' => 5,
            ],
            [
                'name' => 'Envío Brasil',
                'price' => 0,
                'country' => 'Brasil',
                'delivery_days_min' => 5,
                'delivery_days_max' => 10,
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::updateOrCreate(
                ['name' => $method['name']],
                array_merge($method, ['is_active' => true])
            );
        }
    }
}
