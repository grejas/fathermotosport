<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Transportistas disponibles al cargar el tracking de un envío.
 * Las tarifas que ve el cliente se cargan en Opciones de envío (shipping_options).
 */
class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['DHL', 'FedEx', 'Correo nacional'] as $name) {
            ShippingMethod::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
