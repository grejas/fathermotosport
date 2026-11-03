<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingOption;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\PrecioFinal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Descuento por categoría: un porcentaje (con decimales) que se calcula en vivo sobre
 * el precio de lista, con fechas opcionales, que alcanza a las subcategorías y del que
 * se pueden excluir productos. Si choca con el descuento propio del producto gana el
 * que deja el precio más bajo, sin sumarse. Lo que se muestra es lo que se cobra.
 */
class CategoryDiscountTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_21_100001_add_discount_to_categories_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        // Guardar una categoría avisa a la web; en los tests no hay web.
        Http::fake();
        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    private function categoria(string $slug, array $descuento = [], ?Category $padre = null): Category
    {
        return Category::create(array_merge([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'parent_id' => $padre?->id,
            'is_active' => true,
        ], $descuento));
    }

    private function descuento(float $porcentaje, array $extra = []): array
    {
        return array_merge(['discount_percent' => $porcentaje, 'discount_active' => true], $extra);
    }

    private function producto(Category $categoria, float $precio = 430, ?float $oferta = null, string $sku = 'DESC-1'): Product
    {
        $producto = Product::factory()->create([
            'category_id' => $categoria->id,
            'price' => $precio,
            'sale_price' => $oferta,
            'sku' => $sku,
            'slug' => strtolower($sku),
            'is_active' => true,
        ]);

        // Una sola talla con stock, para poder comprarlo.
        $producto->variants()->delete();
        $producto->variants()->create(['sku' => "{$sku}-M", 'size' => 'M', 'stock' => 10, 'is_active' => true, 'price' => $precio]);

        return $producto->fresh();
    }

    /** Lo que devuelve la API para la ficha del producto. */
    private function api(Product $producto): array
    {
        return $this->getJson("/api/v1/products/{$producto->slug}")->assertOk()->json('data');
    }

    private function pedido(Product $producto, array $extra = [], int $cantidad = 1)
    {
        return $this->postJson('/api/v1/orders', array_merge([
            'guest_email' => 'invitado@example.com',
            'items' => [['variant_id' => $producto->variants()->value('id'), 'quantity' => $cantidad]],
            'address' => [
                'full_name' => 'Cliente Prueba', 'phone' => '76543210', 'country' => 'Bolivia',
                'city' => 'Santa Cruz', 'address_line' => 'Av. Prueba 123',
            ],
            'payment_method' => 'stripe',
        ], $extra));
    }

    // ───────────────── Cálculo ─────────────────

    public function test_the_category_discount_lowers_the_final_price_without_touching_the_product(): void
    {
        $producto = $this->producto($this->categoria('cascos-15', $this->descuento(15)));

        $json = $this->api($producto);

        $this->assertSame('365.50', $json['final_price']);
        $this->assertSame(15, $json['discount_percent']);
        $this->assertSame('category', $json['discount_source']);
        // No se sobrescribe nada guardado.
        $this->assertSame('430.00', $json['price']);
        $this->assertNull($json['sale_price']);
        $this->assertDatabaseHas('products', ['id' => $producto->id, 'price' => 430, 'sale_price' => null]);
    }

    public function test_with_its_own_discount_the_lowest_final_price_wins_without_adding_them(): void
    {
        $casos = [
            // categoría => [precio final, porcentaje, origen]
            15 => ['365.50', 15, 'category'],   // 430 −15 % = 365,50 < 387
            5 => ['387.00', 10, 'product'],      // 430 −5 % = 408,50 > 387
            10 => ['387.00', 10, 'product'],     // empate: gana el propio
        ];

        foreach ($casos as $porcentaje => [$final, $mostrado, $origen]) {
            $producto = $this->producto($this->categoria("choque-{$porcentaje}", $this->descuento($porcentaje)), 430, 387, "CHOQUE-{$porcentaje}");

            $json = $this->api($producto);
            $this->assertSame($final, $json['final_price'], "categoría {$porcentaje} %");
            $this->assertSame($mostrado, $json['discount_percent']);
            $this->assertSame($origen, $json['discount_source']);
            $this->assertSame('387.00', $json['sale_price'], 'el descuento propio no se toca');
        }
    }

    public function test_decimal_percentages_round_to_the_cent(): void
    {
        $producto = $this->producto($this->categoria('decimal', $this->descuento(7.5)));
        $json = $this->api($producto);

        $this->assertSame('397.75', $json['final_price']); // 430 × 0,925
        $this->assertSame(7.5, $json['discount_percent']);

        $otro = $this->producto($this->categoria('decimal-2', $this->descuento(12.35)), 99.99, null, 'DEC-2');
        $this->assertSame('87.64', $this->api($otro)['final_price']); // 99,99 × 0,8765 = 87,641…
    }

    public function test_without_any_discount_nothing_changes(): void
    {
        $producto = $this->producto($this->categoria('sin-descuento'), 200, 180);
        $json = $this->api($producto);

        $this->assertSame('180.00', $json['final_price']);
        $this->assertSame(10, $json['discount_percent']);
        $this->assertSame('product', $json['discount_source']);

        $lista = $this->producto($this->categoria('sin-nada'), 200, null, 'SIN-2');
        $json = $this->api($lista);
        $this->assertSame('200.00', $json['final_price']);
        $this->assertSame(0, $json['discount_percent']);
        $this->assertNull($json['discount_source']);
    }

    public function test_the_switch_turned_off_removes_it_and_keeps_the_settings(): void
    {
        $categoria = $this->categoria('apagable', $this->descuento(15));
        $producto = $this->producto($categoria);
        $this->assertSame('365.50', $this->api($producto)['final_price']);

        $categoria->update(['discount_active' => false]);

        $this->assertSame('430.00', $this->api($producto)['final_price']);
        $this->assertSame('15.00', $categoria->fresh()->discount_percent);
    }

    // ───────────────── Fechas ─────────────────

    public function test_dates_are_optional_and_the_end_applies_at_once(): void
    {
        $categoria = $this->categoria('fechas', $this->descuento(15, [
            'discount_starts_at' => '2026-10-22 04:00:00', // 22/10 00:00 en Bolivia
            'discount_ends_at' => '2026-10-25 03:59:00',
        ]));
        $producto = $this->producto($categoria);

        // Antes del inicio: programado, sin descuento.
        $this->assertSame('430.00', $this->api($producto)['final_price']);
        $this->assertSame(Category::DESCUENTO_PROGRAMADO, $categoria->estadoDelDescuento());

        // Dentro de la ventana.
        Carbon::setTestNow('2026-10-22 04:00:00');
        $this->assertSame('365.50', $this->api($producto)['final_price']);

        // Un minuto después del fin ya no aplica, aunque el interruptor siga encendido.
        Carbon::setTestNow('2026-10-25 04:00:00');
        $this->assertSame('430.00', $this->api($producto)['final_price']);
        $this->assertTrue($categoria->fresh()->discount_active);
        $this->assertSame(Category::DESCUENTO_VENCIDO, $categoria->fresh()->estadoDelDescuento());

        // Sin fechas: activo hasta que se apague.
        $categoria->update(['discount_starts_at' => null, 'discount_ends_at' => null]);
        Carbon::setTestNow('2030-01-01 00:00:00');
        $this->assertSame('365.50', $this->api($producto)['final_price']);
    }

    public function test_the_scheduled_task_only_turns_off_expired_switches(): void
    {
        $vencida = $this->categoria('vencida', $this->descuento(10, ['discount_ends_at' => '2026-10-21 11:59:00']));
        $futura = $this->categoria('futura', $this->descuento(10, ['discount_ends_at' => '2026-10-30 00:00:00']));
        $sinFin = $this->categoria('sin-fin', $this->descuento(10));

        $this->artisan('categorias:vencer-descuentos')->assertSuccessful();

        $this->assertFalse($vencida->fresh()->discount_active);
        $this->assertSame('10.00', $vencida->fresh()->discount_percent, 'conserva el porcentaje y las fechas');
        $this->assertTrue($futura->fresh()->discount_active);
        $this->assertTrue($sinFin->fresh()->discount_active);

        $this->assertStringContainsString(
            "Schedule::command('categorias:vencer-descuentos')",
            file_get_contents(base_path('routes/console.php'))
        );
    }

    // ───────────────── Subcategorías y exclusión ─────────────────

    public function test_subcategories_inherit_and_the_highest_percentage_wins(): void
    {
        $padre = $this->categoria('padre', $this->descuento(10));
        $sinPropio = $this->categoria('hija-sin', [], $padre);
        $mayor = $this->categoria('hija-mayor', $this->descuento(15), $padre);
        $menor = $this->categoria('hija-menor', $this->descuento(5), $padre);
        $nieta = $this->categoria('nieta', [], $sinPropio);

        $this->assertSame('387.00', $this->api($this->producto($sinPropio, 430, null, 'SUB-1'))['final_price']);
        $this->assertSame('365.50', $this->api($this->producto($mayor, 430, null, 'SUB-2'))['final_price']);
        $this->assertSame('387.00', $this->api($this->producto($menor, 430, null, 'SUB-3'))['final_price']);
        $this->assertSame('387.00', $this->api($this->producto($nieta, 430, null, 'SUB-4'))['final_price']);
    }

    public function test_an_excluded_product_keeps_only_its_own_discount(): void
    {
        $categoria = $this->categoria('con-excluidos', $this->descuento(15));
        $conPropio = $this->producto($categoria, 430, 400, 'EXC-1');
        $sinPropio = $this->producto($categoria, 430, null, 'EXC-2');
        $normal = $this->producto($categoria, 430, null, 'EXC-3');
        $categoria->productosExcluidos()->sync([$conPropio->id, $sinPropio->id]);

        $json = $this->api($conPropio);
        $this->assertSame('400.00', $json['final_price']);
        $this->assertSame('product', $json['discount_source']);
        $this->assertSame('430.00', $this->api($sinPropio)['final_price']);
        $this->assertSame('365.50', $this->api($normal)['final_price']);
    }

    public function test_an_exclusion_only_covers_the_category_that_excludes(): void
    {
        $padre = $this->categoria('padre-excluye', $this->descuento(20));
        $hija = $this->categoria('hija-propia', $this->descuento(10), $padre);
        $producto = $this->producto($hija);

        $padre->productosExcluidos()->attach($producto->id);

        // Excluido del 20 % del padre, sigue recibiendo el 10 % de su subcategoría.
        $this->assertSame('387.00', $this->api($producto)['final_price']);
    }

    // ───────────────── Panel ─────────────────

    public function test_the_panel_saves_the_discount_with_decimals_dates_and_exclusions(): void
    {
        $this->actingAs($this->admin());
        $categoria = $this->categoria('panel');
        $hija = $this->categoria('panel-hija', [], $categoria);
        $excluido = $this->producto($hija, 430, null, 'PANEL-1');
        $incluido = $this->producto($categoria, 430, null, 'PANEL-2');
        $ajeno = $this->producto($this->categoria('ajena'), 430, null, 'PANEL-3');

        $pagina = Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()]);

        // El selector ofrece solo productos de la categoría y de sus subcategorías.
        $opciones = $pagina->instance()->form->getComponent('data.productosExcluidos')->getOptions();
        $this->assertArrayHasKey($excluido->id, $opciones);
        $this->assertArrayHasKey($incluido->id, $opciones);
        $this->assertArrayNotHasKey($ajeno->id, $opciones);

        $pagina->fillForm([
            'discount_percent' => '7.5',
            'discount_active' => true,
            // Horas de Bolivia en el panel; se guardan en UTC.
            'discount_starts_at' => '2026-10-21 00:00:00',
            'discount_ends_at' => '2026-10-31 23:59:00',
            'productosExcluidos' => [$excluido->id],
        ])->call('save')->assertHasNoFormErrors();

        $categoria->refresh();
        $this->assertSame('7.50', $categoria->discount_percent);
        $this->assertTrue($categoria->discount_active);
        $this->assertSame('2026-10-21 04:00:00', $categoria->discount_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 03:59:00', $categoria->discount_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame([$excluido->id], $categoria->productosExcluidos()->pluck('products.id')->all());

        $this->assertSame('430.00', $this->api($excluido)['final_price']);
        $this->assertSame('397.75', $this->api($incluido)['final_price']);

        Livewire::test(ListCategories::class)->assertSee('-7,5 % vigente');
    }

    public function test_the_panel_rejects_invalid_discounts(): void
    {
        $this->actingAs($this->admin());
        $categoria = $this->categoria('invalida');

        $casos = [
            [['discount_percent' => '0.4', 'discount_active' => true], 'discount_percent'],
            [['discount_percent' => '90.01', 'discount_active' => true], 'discount_percent'],
            [['discount_percent' => '7.555', 'discount_active' => true], 'discount_percent'],
            [['discount_percent' => null, 'discount_active' => true], 'discount_percent'],
            [['discount_percent' => '10', 'discount_active' => true, 'discount_starts_at' => '2026-10-25 10:00:00', 'discount_ends_at' => '2026-10-25 09:00:00'], 'discount_ends_at'],
        ];

        foreach ($casos as [$datos, $campo]) {
            Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
                ->fillForm($datos)
                ->call('save')
                ->assertHasFormErrors([$campo]);
        }

        $this->assertNull($categoria->fresh()->discount_percent);

        // Los extremos sí entran, y apagado no hace falta porcentaje.
        foreach (['0.5', '90'] as $valor) {
            Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
                ->fillForm(['discount_percent' => $valor, 'discount_active' => true])
                ->call('save')
                ->assertHasNoFormErrors();
        }
        Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
            ->fillForm(['discount_percent' => null, 'discount_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_a_new_category_starts_with_the_discount_off(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Nueva', 'slug' => 'nueva'])
            ->assertFormFieldIsHidden('productosExcluidos')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Category::where('slug', 'nueva')->firstOrFail()->discount_active);
    }

    // ───────────────── Cobro ─────────────────

    public function test_the_order_charges_the_final_price_shown(): void
    {
        $producto = $this->producto($this->categoria('cobro', $this->descuento(15)), 430, 387);

        $pedido = $this->pedido($producto, ['expected_subtotal' => '731.00'], 2)->assertCreated()->json('order');

        $this->assertEquals(731.00, $pedido['subtotal']);
        $this->assertEquals(731.00, $pedido['total']);
        $this->assertEquals(365.50, Order::findOrFail($pedido['id'])->items()->value('unit_price'));
    }

    public function test_a_changed_price_rejects_the_order_so_the_web_shows_the_new_total(): void
    {
        $categoria = $this->categoria('vence-al-pagar', $this->descuento(15, ['discount_ends_at' => '2026-10-21 12:05:00']));
        $producto = $this->producto($categoria);

        // El cliente vio 365,50; pasa el fin antes de pagar.
        Carbon::setTestNow('2026-10-21 12:06:00');

        $this->pedido($producto, ['expected_subtotal' => '365.50'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_subtotal']);
        $this->assertSame(0, Order::count());

        // Con el total nuevo pasa, y sin el campo (PayPal Express, web vieja) también.
        $this->pedido($producto, ['expected_subtotal' => '430.00'])->assertCreated();
        $this->pedido($producto)->assertCreated();
        $this->assertSame(['430.00', '430.00'], Order::orderBy('created_at')->pluck('subtotal')->all());
    }

    public function test_paypal_express_also_checks_the_subtotal_shown(): void
    {
        $producto = $this->producto($this->categoria('express', $this->descuento(15)));
        $opcion = ShippingOption::create([
            'country_code' => 'BO', 'method_name' => 'Express DHL', 'min_weight_kg' => 0, 'max_weight_kg' => null,
            'price' => 25, 'currency' => 'USD', 'estimated_days_min' => 5, 'estimated_days_max' => 7, 'is_active' => true,
        ]);
        $express = fn (array $extra) => $this->postJson('/api/v1/orders/paypal-express', array_merge([
            'items' => [['variant_id' => $producto->variants()->value('id'), 'quantity' => 1]],
            'shipping_country_code' => 'BO',
            'shipping_option_id' => $opcion->id,
            'email' => 'escrito@example.com',
        ], $extra));

        $express(['expected_subtotal' => '430.00'])->assertUnprocessable()->assertJsonValidationErrors(['expected_subtotal']);
        $this->assertSame(0, Order::count());

        $pedido = $express(['expected_subtotal' => '365.50'])->assertCreated()->json('order');
        $this->assertEquals(390.50, $pedido['total']);
    }

    public function test_old_orders_keep_their_prices(): void
    {
        $categoria = $this->categoria('historial');
        $producto = $this->producto($categoria);
        $pedido = $this->pedido($producto)->assertCreated()->json('order');

        $categoria->update($this->descuento(50));

        $guardado = Order::findOrFail($pedido['id']);
        $this->assertSame('430.00', $guardado->subtotal);
        $this->assertSame('430.00', $guardado->items()->first()->unit_price);
    }

    public function test_coupons_keep_applying_over_the_discounted_subtotal(): void
    {
        Coupon::create(['code' => 'DIEZ', 'type' => 'percentage', 'value' => 10, 'minimum_amount' => 0, 'is_active' => true]);
        $producto = $this->producto($this->categoria('con-cupon', $this->descuento(15)));

        $pedido = $this->pedido($producto, ['coupon_code' => 'DIEZ'])->assertCreated()->json('order');

        $this->assertEquals(365.50, $pedido['subtotal']);
        $this->assertEquals(36.55, $pedido['discount']);
        $this->assertEquals(328.95, $pedido['total']);
    }

    public function test_the_cart_prices_endpoint_returns_todays_final_price(): void
    {
        $producto = $this->producto($this->categoria('carrito', $this->descuento(15)), 430, 387);
        $inactivo = $this->producto($this->categoria('carrito-2'), 100, null, 'CART-2');
        $inactivo->update(['is_active' => false]);
        $variante = $producto->variants()->value('id');

        $datos = $this->postJson('/api/v1/cart/prices', [
            'variant_ids' => [$variante, $inactivo->variants()->value('id')],
        ])->assertOk()->json('data');

        $this->assertSame($variante, $datos[0]['variant_id']);
        $this->assertTrue($datos[0]['available']);
        $this->assertSame('365.50', $datos[0]['final_price']);
        $this->assertSame('430.00', $datos[0]['price']);
        $this->assertSame(15, $datos[0]['discount_percent']);
        $this->assertSame('category', $datos[0]['discount_source']);
        $this->assertSame(10, $datos[0]['stock']);

        $this->assertFalse($datos[1]['available']);
        $this->assertNull($datos[1]['final_price']);

        $this->postJson('/api/v1/cart/prices', ['variant_ids' => []])->assertUnprocessable();
        $this->postJson('/api/v1/cart/prices', ['variant_ids' => ['no-es-uuid']])->assertUnprocessable();
    }

    public function test_the_upsell_applies_its_percentage_over_the_final_price(): void
    {
        $producto = $this->producto($this->categoria('upsell', $this->descuento(15)));

        $regla = new UpsellRule(['discount_percent' => 10]);
        $regla->setRelation('offerProduct', $producto);

        $this->assertSame(365.50, $regla->precioBase());
        $this->assertSame(328.95, $regla->precioConDescuento());
    }

    // ───────────────── Rendimiento y migración ─────────────────

    public function test_a_listing_reads_the_categories_once(): void
    {
        $categoria = $this->categoria('listado', $this->descuento(15));
        foreach (range(1, 8) as $i) {
            $this->producto($categoria, 100 + $i, null, "LIST-{$i}");
        }
        $categoria->productosExcluidos()->attach(Product::where('sku', 'LIST-1')->value('id'));

        DB::enableQueryLog();
        $datos = $this->getJson('/api/v1/products?category='.$categoria->id.'&per_page=20')->assertOk()->json('data');
        $consultas = collect(DB::getQueryLog())->pluck('query');

        $this->assertCount(8, $datos);
        $this->assertSame(1, $consultas->filter(fn ($q) => str_contains($q, 'category_discount_exclusions'))->count());
        $this->assertSame(1, $consultas->filter(fn ($q) => str_contains($q, '"discount_active"') && str_contains($q, 'from "categories"'))->count());

        $precios = collect($datos)->keyBy('sku');
        $this->assertSame('101.00', $precios['LIST-1']['final_price']);
        $this->assertSame('86.70', $precios['LIST-2']['final_price']);
    }

    public function test_the_migration_is_additive_and_can_run_again(): void
    {
        $categoria = $this->categoria('migracion', $this->descuento(15));
        $producto = $this->producto($categoria);
        $categoria->productosExcluidos()->attach($producto->id);
        $migracion = require base_path(self::MIGRACION);

        $migracion->up();
        $this->assertSame('15.00', $categoria->fresh()->discount_percent);
        $this->assertSame(1, DB::table('category_discount_exclusions')->count());

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('categories', 'discount_percent'));
        $this->assertFalse(Schema::hasTable('category_discount_exclusions'));
        $this->assertDatabaseHas('products', ['id' => $producto->id, 'price' => 430]);

        PrecioFinal::olvidar();
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumns('categories', ['discount_percent', 'discount_active', 'discount_starts_at', 'discount_ends_at']));
        $this->assertFalse($categoria->fresh()->discount_active, 'toda categoría arranca con el descuento apagado');
    }
}
