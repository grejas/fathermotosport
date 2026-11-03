<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderCoupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\CouponService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un cupón con dueño (bienvenida, cumpleaños) solo lo usa ese cliente con sesión
 * iniciada; los cupones sin dueño siguen como antes. Uno de un solo uso no se puede
 * gastar dos veces aunque dos pedidos lleguen a la vez.
 */
class CuponPersonalTest extends TestCase
{
    use RefreshDatabase;

    private User $duenia;

    private User $otro;

    private ProductVariant $variante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->duenia = $this->cliente('duenia@gmail.com');
        $this->otro = $this->cliente('otro@gmail.com');

        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $this->variante = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 10]);
    }

    private function cliente(string $email): User
    {
        return User::create([
            'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
            'first_name' => 'Cliente', 'last_name' => 'Prueba', 'email' => $email,
            'password' => Hash::make('Clave#Segura1'), 'status' => 'active', 'email_verified_at' => now(),
            'phone' => '+59176543210', 'country' => 'BO',
        ]);
    }

    private function cupon(?User $duenio, string $codigo = 'PERSONAL-1'): Coupon
    {
        return Coupon::create([
            'user_id' => $duenio?->id, 'code' => $codigo, 'type' => 'percentage', 'value' => 5,
            'minimum_amount' => 0, 'max_uses' => 1, 'used_count' => 0, 'is_active' => true,
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function pedir(string $codigo, bool $invitado = false)
    {
        return $this->postJson('/api/v1/orders', [
            ...($invitado ? ['guest_email' => 'invitado@example.com'] : []),
            'items' => [['variant_id' => $this->variante->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'paypal',
            'coupon_code' => $codigo,
        ]);
    }

    public function test_solo_la_duenia_con_sesion_puede_validarlo(): void
    {
        $this->cupon($this->duenia);

        $this->postJson('/api/v1/coupons/validate', ['code' => 'PERSONAL-1', 'amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este cupón es personal: inicia sesión con la cuenta a la que pertenece.');

        Sanctum::actingAs($this->otro);
        $this->postJson('/api/v1/coupons/validate', ['code' => 'PERSONAL-1', 'amount' => 100])->assertStatus(422);

        Sanctum::actingAs($this->duenia);
        $this->postJson('/api/v1/coupons/validate', ['code' => 'PERSONAL-1', 'amount' => 100])
            ->assertOk()->assertJsonPath('discount', 5);
    }

    public function test_un_cupon_sin_duenio_sigue_sirviendo_para_cualquiera(): void
    {
        $this->cupon(null, 'GENERAL-10');

        $this->postJson('/api/v1/coupons/validate', ['code' => 'GENERAL-10', 'amount' => 100])->assertOk();
        $this->pedir('GENERAL-10', invitado: true)->assertCreated();
    }

    public function test_un_invitado_u_otro_cliente_no_pueden_usarlo_en_un_pedido(): void
    {
        $cupon = $this->cupon($this->duenia);

        $this->pedir('PERSONAL-1', invitado: true)->assertStatus(422)->assertJsonValidationErrors('coupon_code');

        Sanctum::actingAs($this->otro);
        $this->pedir('PERSONAL-1')->assertStatus(422)->assertJsonValidationErrors('coupon_code');

        $this->assertSame(0, $cupon->fresh()->used_count);
        $this->assertSame(0, Order::count());
    }

    public function test_la_duenia_lo_usa_una_sola_vez(): void
    {
        $cupon = $this->cupon($this->duenia);
        Sanctum::actingAs($this->duenia);

        $this->pedir('PERSONAL-1')->assertCreated()->assertJsonPath('order.discount', '5.00');
        $this->pedir('PERSONAL-1')->assertStatus(422)->assertJsonValidationErrors('coupon_code');

        $this->assertSame(1, $cupon->fresh()->used_count);
        $this->assertSame(1, OrderCoupon::count());
    }

    /**
     * Dos pedidos simultáneos: ambos validaron el cupón cuando aún tenía su uso, y el otro
     * lo gasta justo antes de que este lo consuma. La suma condicional lo detecta y el
     * pedido entero se deshace (en MySQL, además, la fila queda bloqueada con lockForUpdate).
     */
    public function test_con_dos_pedidos_a_la_vez_el_segundo_no_lo_gasta_y_se_deshace(): void
    {
        $cupon = $this->cupon($this->duenia);
        Sanctum::actingAs($this->duenia);

        // Simula al otro pedido: gasta el cupón justo después de la última lectura del
        // cupón y antes de la actualización condicional.
        $lecturas = 0;
        DB::listen(function ($consulta) use (&$lecturas, $cupon) {
            if (str_starts_with(strtolower($consulta->sql), 'select') && str_contains($consulta->sql, '"coupons"') && ++$lecturas === 2) {
                DB::table('coupons')->where('id', $cupon->id)->update(['used_count' => 1]);
            }
        });

        // "Ya se usó" sale de la suma condicional: no encontró usos libres y no sumó.
        $this->pedir('PERSONAL-1')->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Este cupón ya se usó.');

        // La simulación corre dentro de la misma transacción, así que también se deshace;
        // un pedido real la habría confirmado aparte. Lo que importa: este no dejó nada.
        $this->assertSame(0, Order::count(), 'el pedido se deshizo entero');
        $this->assertSame(0, OrderCoupon::count());
    }

    public function test_aplicar_el_cupon_ya_gastado_lanza_error_y_no_suma_usos(): void
    {
        $cupon = $this->cupon($this->duenia);
        $cupon->update(['used_count' => 1]);

        $this->expectException(ValidationException::class);

        try {
            app(CouponService::class)->applyCoupon('PERSONAL-1', (string) str()->uuid(), 100, $this->duenia);
        } finally {
            $this->assertSame(1, $cupon->fresh()->used_count);
        }
    }
}
