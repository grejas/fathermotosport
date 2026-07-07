<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettingsPage;
use App\Filament\Resources\BannerResource;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\ProductResource;
use App\Mail\WelcomeEmployeeMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function make(string $slug, string $email): User
    {
        return User::create([
            'role_id' => Role::where('slug', $slug)->firstOrFail()->id,
            'first_name' => ucfirst($slug),
            'last_name' => 'Test',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_empleado_puede_acceder_a_recursos_de_gestion_pero_no_al_equipo(): void
    {
        $this->actingAs($this->make('empleado', 'emp@test.com'));

        // SÍ puede gestionar / ver
        $this->assertTrue(ProductResource::canAccess());
        $this->assertTrue(CouponResource::canAccess());
        $this->assertTrue(BannerResource::canAccess());
        $this->assertTrue(CustomerResource::canAccess());
        $this->assertTrue(StoreSettingsPage::canAccess());

        // NO puede gestionar el equipo (crear/editar empleados)
        $this->assertFalse(EmployeeResource::canAccess());
    }

    public function test_admin_puede_acceder_al_equipo(): void
    {
        $this->actingAs($this->make('administrador', 'boss@test.com'));

        $this->assertTrue(EmployeeResource::canAccess());
        $this->assertTrue(ProductResource::canAccess());
    }

    public function test_cliente_no_puede_acceder_al_panel(): void
    {
        $cliente = $this->make('cliente', 'cliente@test.com');

        $this->assertFalse($cliente->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertFalse($cliente->isStaff());
    }

    public function test_empleado_si_puede_acceder_al_panel(): void
    {
        $empleado = $this->make('empleado', 'emp2@test.com');

        $this->assertTrue($empleado->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertTrue($empleado->isStaff());
    }

    public function test_empleado_renderiza_panel_sin_menu_de_equipo(): void
    {
        $empleado = $this->make('empleado', 'emp3@test.com');

        $this->actingAs($empleado)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('/admin/employees'); // sin acceso a la sección Equipo
    }

    public function test_cliente_es_bloqueado_del_panel(): void
    {
        $cliente = $this->make('cliente', 'cli2@test.com');

        $response = $this->actingAs($cliente)->get('/admin');

        // Filament bloquea con 403 (o redirect); nunca 200.
        $this->assertContains($response->getStatusCode(), [403, 302]);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_admin_crea_empleado_con_rol_correcto_y_envia_email(): void
    {
        Mail::fake();
        $admin = $this->make('administrador', 'boss2@test.com');

        Livewire::actingAs($admin)
            ->test(CreateEmployee::class)
            ->fillForm([
                'first_name' => 'Juan',
                'last_name' => 'Empleado',
                'email' => 'empleado@fathermotosport.com',
                'phone' => '700',
                'password' => 'Empleado123!',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $emp = User::where('email', 'empleado@fathermotosport.com')->first();

        $this->assertNotNull($emp);
        $this->assertSame('empleado', $emp->role->slug);   // rol asignado automáticamente
        $this->assertSame('active', $emp->status);
        $this->assertNotNull($emp->last_password_change);

        Mail::assertSent(WelcomeEmployeeMail::class, fn ($mail) => $mail->employee->id === $emp->id
            && $mail->temporaryPassword === 'Empleado123!');
    }
}
