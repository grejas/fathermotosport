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
    }
}
