<?php

namespace Database\Seeders;

use App\Models\StoreConfig;
use Illuminate\Database\Seeder;

class StoreConfigSeeder extends Seeder
{
    public function run(): void
    {
        StoreConfig::updateOrCreate(
            ['id' => 1],
            [
                'store_name' => 'FatherMotoSport',
                'logo_url' => null,
                'favicon_url' => null,
                'phone' => '+591 68736384',
                'email' => 'contacto@fathermotosport.com',
                'currency' => 'USD',
                'whatsapp' => '+59168736384',
                'facebook' => 'https://facebook.com/fathermotosport',
                'instagram' => 'https://instagram.com/fathermotosport',
                'youtube' => 'https://youtube.com/@fathermotosport',
                'maintenance_mode' => false,
                'payment_keys' => [
                    'paypal' => ['mode' => 'sandbox', 'client_id' => '', 'secret' => ''],
                    'stripe' => ['public_key' => '', 'secret_key' => ''],
                    'mercadopago' => ['public_key' => '', 'access_token' => ''],
                ],
            ]
        );
    }
}
