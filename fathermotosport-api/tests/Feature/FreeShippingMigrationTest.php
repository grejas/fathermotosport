<?php

namespace Tests\Feature;

use App\Models\ShippingOption;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Envío gratis y sin límite de peso a Europa, México, EE.UU. y Guatemala. */
class FreeShippingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_helmets_ship_free_to_spain_guatemala_mexico_and_us(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Como en producción: Bolivia y Brasil con tarifa de 1 a 2 kg.
        foreach (['BO', 'BR'] as $pais) {
            ShippingOption::create([
                'country_code' => $pais, 'method_name' => 'Estándar', 'min_weight_kg' => 1,
                'max_weight_kg' => 2, 'price' => 0, 'currency' => 'USD', 'is_active' => true,
            ]);
        }
        // Corre de nuevo sobre los datos sembrados: debe ser idempotente.
        (require database_path('migrations/2026_11_17_200002_free_shipping_europe_mx_us_gt.php'))->up();
        (require database_path('migrations/2026_11_17_200002_free_shipping_europe_mx_us_gt.php'))->up();

        foreach (['ES', 'DE', 'GR', 'GB', 'MX', 'US', 'GT'] as $pais) {
            foreach ([0.3, 3.2, 12] as $peso) {
                $opciones = ShippingOption::availableFor($pais, $peso);
                $this->assertCount(1, $opciones, "{$pais} {$peso} kg");
                $this->assertSame('0.00', $opciones->first()->price);
            }
        }

        $this->assertSame(1, ShippingOption::forCountry('ES')->count(), 'sin duplicados');

        $this->assertNotEmpty(ShippingOption::availableFor('BO', 12), 'BO sin límite de peso');
    }

    public function test_free_shipping_to_every_country_in_the_americas(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Como en producción: Brasil a 75 USD de 1 a 2 kg, con plazos.
        ShippingOption::forCountry('BR')->delete();
        ShippingOption::create([
            'country_code' => 'BR', 'method_name' => 'Estándar', 'min_weight_kg' => 1, 'max_weight_kg' => 2,
            'price' => 75, 'currency' => 'USD', 'is_active' => true, 'estimated_days_min' => 5, 'estimated_days_max' => 7,
        ]);
        $migracion = require database_path('migrations/2026_11_17_200006_free_shipping_all_americas.php');
        $migracion->up();
        $migracion->up();

        foreach (array_keys(\App\Support\ShippingCountries::AMERICAS) as $pais) {
            foreach ([0.3, 3.2, 12] as $peso) {
                $opciones = ShippingOption::availableFor($pais, $peso);
                $this->assertCount(1, $opciones, "{$pais} {$peso} kg");
                $this->assertSame('0.00', $opciones->first()->price, $pais);
            }
        }

        $this->assertSame(7, ShippingOption::forCountry('BR')->first()->estimated_days_max, 'conserva los plazos');
    }
}
