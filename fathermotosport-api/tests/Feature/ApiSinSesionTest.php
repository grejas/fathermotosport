<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bootstrap/app.php: una ruta protegida de /api sin token responde 401 JSON aunque el
 * cliente no mande "Accept: application/json" (antes intentaba redirigir a una ruta
 * "login" que no existe y daba 500). El panel /admin sigue con su propio login.
 */
class ApiSinSesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sin_token_y_sin_accept_json_responde_401_json(): void
    {
        foreach (['/api/v1/auth/me', '/api/v1/user/profile', '/api/v1/user/orders'] as $ruta) {
            $respuesta = $this->get($ruta); // sin cabecera Accept

            $respuesta->assertStatus(401);
            $this->assertStringContainsString('application/json', (string) $respuesta->headers->get('Content-Type'), $ruta);
            $respuesta->assertExactJson(['message' => 'No autenticado']);
        }
    }

    public function test_sin_token_con_accept_json_tambien_responde_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401)->assertJson(['message' => 'No autenticado']);
        $this->postJson('/api/v1/user/complete-profile')->assertStatus(401);
    }

    public function test_un_error_de_validacion_en_la_api_sale_en_json_aunque_no_pidan_json(): void
    {
        // Antes, sin Accept JSON, un error de validación redirigía (302) en vez de dar 422.
        $respuesta = $this->post('/api/v1/coupons/validate', []);

        $respuesta->assertStatus(422);
        $this->assertArrayHasKey('code', $respuesta->json('errors'));
    }

    public function test_el_panel_sigue_redirigiendo_a_su_propio_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/customers')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }
}
