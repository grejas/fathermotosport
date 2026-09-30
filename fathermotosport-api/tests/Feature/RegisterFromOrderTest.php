<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingOption;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Crear una cuenta a partir de un pedido de invitado pagado.
 * Lo delicado es que nadie pueda reclamar un pedido ajeno: hace falta el token
 * del pedido, no alcanza con conocer su id.
 */
class RegisterFromOrderTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Clave#2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    /** Pedido de invitado pagado, como queda tras un PayPal Express capturado. */
    private function pedidoPagadoDeInvitado(array $overrides = []): Order
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        ShippingOption::create([
            'country_code' => 'BO', 'method_name' => 'Estándar', 'min_weight_kg' => 0,
            'max_weight_kg' => null, 'price' => 0, 'currency' => 'USD', 'is_active' => true,
        ]);

        $order = Order::findOrFail(
            $this->postJson('/api/v1/orders', [
                'guest_email' => 'ana.perez@example.com',
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
                'address' => [
                    'full_name' => 'Ana Pérez', 'phone' => '76543210', 'country' => 'Bolivia',
                    'city' => 'Santa Cruz', 'address_line' => 'Av. Siempre Viva 742',
                ],
                'payment_method' => 'paypal',
            ])->json('order.id')
        );

        $order->update(array_merge([
            'payment_status' => 'paid',
            'status' => 'processing',
            'email_verificado_por' => 'paypal',
        ], $overrides));

        return $order->fresh('address');
    }

    public function test_the_order_token_is_required_to_claim_an_order(): void
    {
        $order = $this->pedidoPagadoDeInvitado();

        // Conocer el id no alcanza.
        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertForbidden();

        // Un token inventado tampoco.
        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => 'token-incorrecto'])->assertForbidden();

        $this->assertSame(0, User::where('email', 'ana.perez@example.com')->count());
    }

    public function test_creates_the_account_with_the_order_data_and_logs_in(): void
    {
        $order = $this->pedidoPagadoDeInvitado();

        $respuesta = $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $order->access_token])
            ->assertCreated()
            ->assertJsonPath('user.email', 'ana.perez@example.com')
            ->assertJsonPath('user.first_name', 'Ana')
            ->assertJsonPath('user.last_name', 'Pérez')
            ->assertJsonStructure(['token', 'welcome_coupon' => ['code', 'value'], 'linked_orders']);

        $user = User::where('email', 'ana.perez@example.com')->firstOrFail();
        $this->assertSame('76543210', $user->phone);
        $this->assertNotNull($user->email_verified_at, 'el email lo verificó PayPal');
        $this->assertSame($user->id, $order->fresh()->user_id);

        // El token devuelto sirve de verdad.
        $this->withHeader('Authorization', 'Bearer '.$respuesta->json('token'))
            ->getJson('/api/v1/user/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_welcome_coupon_is_not_applied_to_the_order_already_paid(): void
    {
        $order = $this->pedidoPagadoDeInvitado();
        $totalAntes = $order->total;

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $order->access_token])->assertCreated();

        $order->refresh();
        $this->assertSame($totalAntes, $order->total, 'el pedido pagado no cambia de monto');
        $this->assertSame('0.00', $order->discount);
        $this->assertDatabaseHas('coupons', ['used_count' => 0]);
    }

    public function test_previous_guest_orders_with_the_same_email_are_linked(): void
    {
        $viejo = $this->pedidoPagadoDeInvitado();
        $nuevo = $this->pedidoPagadoDeInvitado();

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $nuevo->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $nuevo->access_token])
            ->assertCreated()
            ->assertJsonPath('linked_orders', 2);

        $user = User::where('email', 'ana.perez@example.com')->firstOrFail();
        $this->assertSame($user->id, $viejo->fresh()->user_id);
    }

    public function test_orders_with_an_email_typed_by_hand_are_not_claimed(): void
    {
        // Pedido anterior con el mismo email pero escrito en el checkout, no verificado.
        $aMano = $this->pedidoPagadoDeInvitado(['email_verificado_por' => null]);
        $dePaypal = $this->pedidoPagadoDeInvitado();

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $dePaypal->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $dePaypal->access_token])->assertCreated();

        // El pedido con email a mano sí se vincula porque el que abre la cuenta SÍ
        // está verificado por PayPal: lo que manda es el origen del pedido reclamado.
        $this->assertNotNull($aMano->fresh()->user_id);
    }

    public function test_an_unverified_order_only_claims_itself(): void
    {
        $otro = $this->pedidoPagadoDeInvitado();
        $aMano = $this->pedidoPagadoDeInvitado(['email_verificado_por' => null]);

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $aMano->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $aMano->access_token])
            ->assertCreated()
            ->assertJsonPath('linked_orders', 1);

        // El email no está verificado: no se toca el historial de esa casilla.
        $this->assertNull($otro->fresh()->user_id);
    }

    public function test_an_existing_email_is_invited_to_log_in_instead(): void
    {
        $order = $this->pedidoPagadoDeInvitado();
        User::factory()->create(['email' => 'ana.perez@example.com']);

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $order->access_token])
            ->assertStatus(409)
            ->assertJsonPath('should_login', true);

        // El pedido NO se vincula sin autenticarse.
        $this->assertNull($order->fresh()->user_id);
    }

    public function test_unpaid_or_already_claimed_orders_are_rejected(): void
    {
        $sinPagar = $this->pedidoPagadoDeInvitado(['payment_status' => 'pending']);
        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $sinPagar->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $sinPagar->access_token])->assertStatus(409);

        $conDueno = $this->pedidoPagadoDeInvitado();
        $conDueno->update(['user_id' => User::factory()->create()->id]);
        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $conDueno->id,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Order-Token' => $conDueno->access_token])->assertStatus(409);
    }

    public function test_the_password_must_meet_the_same_rules_as_a_normal_registration(): void
    {
        $order = $this->pedidoPagadoDeInvitado();

        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ], ['X-Order-Token' => $order->access_token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        // Sin confirmar.
        $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id,
            'password' => self::PASSWORD,
            'password_confirmation' => 'otra-cosa',
        ], ['X-Order-Token' => $order->access_token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
