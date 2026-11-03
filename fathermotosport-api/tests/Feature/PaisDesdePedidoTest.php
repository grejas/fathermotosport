<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Paises;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Crear cuenta desde el pedido" copiaba el país del pedido (un nombre: "Bolivia") a
 * users.country, que es de 2 letras: con MySQL estricto el alta fallaba. Ahora se
 * convierte a código ISO, y si no se reconoce la cuenta queda sin país.
 *
 * SQLite no controla el largo de las columnas: un trigger reproduce el error de MySQL
 * estricto ("Data too long") para que el test falle como fallaba en producción.
 */
class PaisDesdePedidoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave#2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        foreach (['insert', 'update'] as $evento) {
            DB::statement("CREATE TRIGGER users_country_estricto_{$evento} BEFORE ".strtoupper($evento).' ON users
                WHEN length(NEW.country) > 2
                BEGIN SELECT RAISE(ABORT, \'Data too long for column country\'); END');
        }
    }

    public static function nombres(): array
    {
        return [
            'es' => ['Estados Unidos', 'US'],
            'en' => ['United States', 'US'],
            'pt con acento' => ['Bolívia', 'BO'],
            'es sin acento' => ['Bolivia', 'BO'],
            'Brasil' => ['Brasil', 'BR'],
            'Brazil' => ['Brazil', 'BR'],
            'checkout es' => ['República Checa', 'CZ'],
            'checkout pt' => ['República Tcheca', 'CZ'],
            'mayúsculas y espacios' => ['  ESPAÑA ', 'ES'],
            'código' => ['bo', 'BO'],
        ];
    }

    #[DataProvider('nombres')]
    public function test_reconoce_el_pais_en_es_pt_y_en(string $nombre, string $iso): void
    {
        $this->assertSame($iso, Paises::iso($nombre));
    }

    public function test_un_pais_desconocido_queda_en_null(): void
    {
        $this->assertNull(Paises::iso('Narnia'));
        $this->assertNull(Paises::iso(''));
        $this->assertNull(Paises::iso(null));
    }

    public function test_el_trigger_reproduce_mysql_estricto(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->where('id', User::query()->value('id'))->update(['country' => 'Bolivia']);
    }

    private function pedidoPagado(string $pais, string $telefono = '76543210'): Order
    {
        $product = Product::factory()->create(['price' => 100, 'sale_price' => null, 'weight' => 1]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock' => 5]);

        $order = Order::findOrFail($this->postJson('/api/v1/orders', [
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address' => [
                'full_name' => 'Ana Pérez', 'phone' => $telefono, 'country' => $pais,
                'city' => 'Ciudad', 'address_line' => 'Calle 1',
            ],
            'payment_method' => 'paypal',
        ])->json('order.id'));

        $order->update(['payment_status' => 'paid', 'status' => 'processing', 'email_verificado_por' => 'paypal']);

        return $order;
    }

    private function crearCuenta(Order $order)
    {
        return $this->postJson('/api/v1/auth/register-from-order', [
            'order_id' => $order->id, 'password' => self::CLAVE, 'password_confirmation' => self::CLAVE,
        ], ['X-Order-Token' => $order->access_token]);
    }

    public function test_crear_cuenta_desde_un_pedido_con_nombre_de_pais_ya_no_falla(): void
    {
        $this->crearCuenta($this->pedidoPagado('Estados Unidos', '305 555 0123'))->assertCreated();

        $user = User::where('email', 'invitado@example.com')->firstOrFail();
        $this->assertSame('US', $user->country);
        $this->assertSame('+13055550123', $user->phone);
    }

    public function test_un_pais_que_no_se_reconoce_deja_la_cuenta_sin_pais_en_vez_de_fallar(): void
    {
        $this->crearCuenta($this->pedidoPagado('Narnia', 'sin numero'))->assertCreated();

        $user = User::where('email', 'invitado@example.com')->firstOrFail();
        $this->assertNull($user->country);
        $this->assertNull($user->phone);
        $this->assertTrue($user->debeVerVentanaDePerfil(), 'se le pedirán después');
    }
}
