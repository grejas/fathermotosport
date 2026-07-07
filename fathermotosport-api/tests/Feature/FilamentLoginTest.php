<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        return User::create([
            'role_id' => Role::where('slug', 'administrador')->firstOrFail()->id,
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin@fathermotosport.com',
            'password' => Hash::make('Admin123!'),
            'status' => 'active',
        ]);
    }

    public function test_admin_inicia_sesion_correctamente(): void
    {
        $admin = $this->admin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@fathermotosport.com',
                'password' => 'Admin123!',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_credenciales_invalidas_no_autentican(): void
    {
        $this->admin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@fathermotosport.com',
                'password' => 'password-incorrecta',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }
}
