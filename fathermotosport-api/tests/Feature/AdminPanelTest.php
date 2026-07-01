<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
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

    public function test_login_page_loads(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('FatherMotoSport');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_can_render_dashboard_with_widgets(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_can_render_each_resource_index(): void
    {
        $admin = $this->admin();

        $pages = [
            '/admin/products',
            '/admin/categories',
            '/admin/brands',
            '/admin/orders',
            '/admin/customers',
            '/admin/coupons',
            '/admin/banners',
            '/admin/inventory-movements',
            '/admin/shipping-methods',
            '/admin/reviews',
            '/admin/store-settings-page',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }
}
