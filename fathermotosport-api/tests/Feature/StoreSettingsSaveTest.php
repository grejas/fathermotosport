<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettingsPage;
use App\Models\StoreConfig;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    public function test_save_persists_to_db_without_touching_env(): void
    {
        $this->actingAs($this->admin());

        $envBefore = file_get_contents(base_path('.env'));

        Livewire::test(StoreSettingsPage::class)
            ->set('data.store_name', 'FatherMotoSport')
            ->set('data.phone', '+591 700-12345')
            ->set('data.currency', 'USD')
            ->set('data.stripe_secret', 'sk_test_NUEVA_CLAVE')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified();

        // Persistió en la tabla store_config.
        $config = StoreConfig::first();
        $this->assertSame('+591 700-12345', $config->phone);
        // La credencial de pago quedó en la DB (payment_keys), no en el .env.
        $this->assertSame('sk_test_NUEVA_CLAVE', data_get($config->payment_keys, 'stripe.secret_key'));

        // El .env NO fue modificado por el guardado.
        $this->assertSame($envBefore, file_get_contents(base_path('.env')), 'El save() no debe modificar el .env');
    }
}
