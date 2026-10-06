<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Selector visible "Ordenar por / Dirección" en Productos y Clientes, y su convivencia
 * con el orden por clic en el encabezado: manda el último que se usó.
 */
class AdminSortSelectorTest extends TestCase
{
    use RefreshDatabase;

    private Product $a;

    private Product $b;

    private Product $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        // Se parte de cero para poder comparar el orden completo.
        Product::query()->forceDelete();
        User::whereHas('role', fn ($q) => $q->where('slug', 'cliente'))->forceDelete();
    }

    private function categoria(string $nombre): Category
    {
        return Category::create(['name' => $nombre, 'slug' => Str::slug($nombre).'-'.Str::random(5)]);
    }

    private function producto(array $atributos, array $stocks): Product
    {
        $product = Product::factory()->create($atributos);
        $product->variants()->delete();

        foreach ($stocks as $i => $stock) {
            ProductVariant::factory()->for($product)->create(['size' => ['S', 'M', 'L'][$i], 'stock' => $stock]);
        }

        return $product;
    }

    /** A: Botas, $300, stock 10, activo, hace 2 días. B: Cascos, $100, stock 1, inactivo, hoy. C: Cascos, $200, stock 9, activo, ayer. */
    private function tresProductos(): void
    {
        $cascos = $this->categoria('Cascos');
        $botas = $this->categoria('Botas');

        $this->a = $this->producto(['name' => 'Alfa', 'sku' => 'SKU-C', 'price' => 300, 'is_active' => true,
            'category_id' => $botas->id, 'created_at' => now()->subDays(2)], [5, 5]);
        $this->b = $this->producto(['name' => 'Beta', 'sku' => 'SKU-A', 'price' => 100, 'is_active' => false,
            'category_id' => $cascos->id, 'created_at' => now()], [1]);
        $this->c = $this->producto(['name' => 'Gama', 'sku' => 'SKU-B', 'price' => 200, 'is_active' => true,
            'category_id' => $cascos->id, 'created_at' => now()->subDay()], [3, 3, 3]);
    }

    private function cliente(array $atributos, int $pedidos = 0): User
    {
        $creado = $atributos['created_at'] ?? null;
        unset($atributos['created_at']);

        $user = User::create($atributos + [
            'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        if ($creado) {
            $user->forceFill(['created_at' => $creado])->save();
        }

        for ($i = 0; $i < $pedidos; $i++) {
            Order::create([
                'user_id' => $user->id, 'status' => 'processing', 'subtotal' => 10, 'discount' => 0,
                'shipping' => 0, 'tax' => 0, 'total' => 10, 'payment_status' => 'paid',
                'shipping_status' => 'pending', 'payment_method' => 'stripe', 'country' => 'Bolivia',
            ]);
        }

        return $user;
    }

    private function ordenar(string $pagina, string $columna, ?string $direccion = 'asc')
    {
        return Livewire::test($pagina)->filterTable('orden', ['columna' => $columna, 'direccion' => $direccion]);
    }

    // ───────────────────────── Productos ─────────────────────────

    public function test_products_sort_by_each_option_both_ways(): void
    {
        $this->tresProductos();
        [$a, $b, $c] = [$this->a, $this->b, $this->c];

        $casos = [
            'name' => [$a, $b, $c],
            'sku' => [$b, $c, $a],
            'price' => [$b, $c, $a],
            'variants_sum_stock' => [$b, $c, $a],
            'created_at' => [$a, $c, $b],
            // Botas antes que Cascos; dentro de Cascos el desempate es la clave.
            'category.name' => [$a],
            // Inactivo primero en ascendente.
            'is_active' => [$b],
        ];

        foreach ($casos as $columna => $ascendente) {
            $asc = $this->ordenar(ListProducts::class, $columna, 'asc');
            $desc = $this->ordenar(ListProducts::class, $columna, 'desc');

            if (count($ascendente) === 3) {
                $asc->assertCanSeeTableRecords($ascendente, inOrder: true);
                $desc->assertCanSeeTableRecords(array_reverse($ascendente), inOrder: true);

                continue;
            }

            // Columnas con empate: alcanza con que el primero quede primero y al revés último.
            $primero = $ascendente[0];
            $resto = array_values(array_filter([$a, $b, $c], fn ($p) => $p->isNot($primero)));
            foreach ($resto as $otro) {
                $asc->assertCanSeeTableRecords([$primero, $otro], inOrder: true);
                $desc->assertCanSeeTableRecords([$otro, $primero], inOrder: true);
            }
        }
    }

    public function test_choosing_only_the_column_means_ascending_and_the_selector_shows_it(): void
    {
        $this->tresProductos();

        $this->ordenar(ListProducts::class, 'price', null)
            ->assertSet('tableSortColumn', 'price')
            ->assertSet('tableSortDirection', 'asc')
            ->assertSet('tableFilters.orden.direccion', 'asc')
            ->assertCanSeeTableRecords([$this->b, $this->c, $this->a], inOrder: true);
    }

    public function test_the_selector_works_together_with_search(): void
    {
        $this->tresProductos();
        $otro = $this->producto(['name' => 'Zeta sin coincidencia', 'price' => 1], [1]);

        Livewire::test(ListProducts::class)
            ->searchTable('SKU-')
            ->filterTable('orden', ['columna' => 'price', 'direccion' => 'desc'])
            ->assertCanSeeTableRecords([$this->a, $this->c, $this->b], inOrder: true)
            ->assertCanNotSeeTableRecords([$otro]);
    }

    public function test_changing_another_filter_keeps_the_chosen_order(): void
    {
        $this->tresProductos();

        $this->ordenar(ListProducts::class, 'price', 'desc')
            ->filterTable('is_active', true)
            ->assertSet('tableSortColumn', 'price')
            ->assertSet('tableSortDirection', 'desc')
            ->assertCanSeeTableRecords([$this->a, $this->c], inOrder: true)
            ->assertCanNotSeeTableRecords([$this->b]);
    }

    public function test_a_header_click_after_the_selector_wins_and_updates_the_selector(): void
    {
        $this->tresProductos();

        $this->ordenar(ListProducts::class, 'price', 'desc')
            ->sortTable('name')
            ->assertSet('tableFilters.orden.columna', 'name')
            ->assertSet('tableFilters.orden.direccion', 'asc')
            ->assertCanSeeTableRecords([$this->a, $this->b, $this->c], inOrder: true);
    }

    public function test_the_selector_after_a_header_click_wins(): void
    {
        $this->tresProductos();

        Livewire::test(ListProducts::class)
            ->sortTable('name')
            ->filterTable('orden', ['columna' => 'price', 'direccion' => 'desc'])
            ->assertSet('tableSortColumn', 'price')
            ->assertCanSeeTableRecords([$this->a, $this->c, $this->b], inOrder: true);
    }

    public function test_reset_goes_back_to_newest_first(): void
    {
        $this->tresProductos();

        // Por defecto: B (hoy), C (ayer), A (hace 2 días).
        $this->ordenar(ListProducts::class, 'price', 'desc')
            ->call('resetTableFiltersForm')
            ->assertSet('tableSortColumn', null)
            ->assertSet('tableFilters.orden.columna', null)
            ->assertCanSeeTableRecords([$this->b, $this->c, $this->a], inOrder: true);

        // Quitar el indicador "Orden: …" hace lo mismo.
        $this->ordenar(ListProducts::class, 'price', 'desc')
            ->call('removeTableFilter', 'orden')
            ->assertSet('tableSortColumn', null)
            ->assertCanSeeTableRecords([$this->b, $this->c, $this->a], inOrder: true);
    }

    public function test_the_chosen_order_shows_as_an_indicator(): void
    {
        $this->tresProductos();

        $this->ordenar(ListProducts::class, 'variants_sum_stock', 'desc')
            ->assertSee('Orden: Stock total (descendente)');
    }

    public function test_both_selectors_are_in_the_filters_panel(): void
    {
        foreach ([ListProducts::class, ListCustomers::class] as $pagina) {
            Livewire::test($pagina)
                ->assertSee('Ordenar por')
                ->assertSee('Dirección')
                ->assertSee('Por defecto (más recientes primero)')
                ->assertSee('Ascendente')
                ->assertSee('Descendente');
        }
    }

    // ───────────────────────── Clientes ─────────────────────────

    public function test_customers_sort_by_each_option_both_ways_and_with_search(): void
    {
        $ana = $this->cliente(['first_name' => 'Ana', 'last_name' => 'Zeta', 'email' => 'zz@test.com',
            'country' => 'BO', 'created_at' => now()->subDays(2)], pedidos: 2);
        $beto = $this->cliente(['first_name' => 'Beto', 'last_name' => 'Alfa', 'email' => 'aa@test.com',
            'country' => 'PE', 'created_at' => now()], pedidos: 0);
        $caro = $this->cliente(['first_name' => 'Caro', 'last_name' => 'Medio', 'email' => 'mm@test.com',
            'country' => 'AR', 'created_at' => now()->subDay()], pedidos: 5);

        $casos = [
            'full_name' => [$ana, $beto, $caro],
            'email' => [$beto, $caro, $ana],
            'country' => [$caro, $ana, $beto],
            'created_at' => [$ana, $caro, $beto],
            'orders_count' => [$beto, $ana, $caro],
        ];

        foreach ($casos as $columna => $ascendente) {
            $this->ordenar(ListCustomers::class, $columna, 'asc')
                ->assertCanSeeTableRecords($ascendente, inOrder: true);
            $this->ordenar(ListCustomers::class, $columna, 'desc')
                ->assertCanSeeTableRecords(array_reverse($ascendente), inOrder: true);
        }

        // Con búsqueda: solo quedan los que coinciden, en el orden elegido.
        Livewire::test(ListCustomers::class)
            ->searchTable('test.com')
            ->filterTable('orden', ['columna' => 'orders_count', 'direccion' => 'desc'])
            ->assertCanSeeTableRecords([$caro, $ana, $beto], inOrder: true);

        Livewire::test(ListCustomers::class)
            ->searchTable('Ana')
            ->filterTable('orden', ['columna' => 'orders_count', 'direccion' => 'desc'])
            ->assertCanSeeTableRecords([$ana])
            ->assertCanNotSeeTableRecords([$beto, $caro]);
    }

    // ─────────────── Panel plegable, indicador y persistencia ───────────────

    public function test_the_filters_panel_starts_collapsed_with_a_labelled_button(): void
    {
        foreach ([ListProducts::class, ListCustomers::class] as $pagina) {
            Livewire::test($pagina)
                // Alpine arranca el panel cerrado; se abre con el botón.
                ->assertSeeHtml('areFiltersOpen: false')
                ->assertSee('Filtros y orden');
        }
    }

    public function test_the_order_indicator_stays_visible_with_the_panel_collapsed(): void
    {
        $this->tresProductos();

        $this->ordenar(ListProducts::class, 'variants_sum_stock', 'desc')
            ->assertSeeHtml('areFiltersOpen: false')
            ->assertSee('Orden: Stock total (descendente)');

        // Y al volver a la página, con el orden recordado, el indicador sigue ahí.
        Livewire::test(ListProducts::class)
            ->assertSeeHtml('areFiltersOpen: false')
            ->assertSee('Orden: Stock total (descendente)');
    }

    public function test_sku_and_created_cannot_be_hidden(): void
    {
        $tabla = Livewire::test(ListProducts::class)->instance()->getTable();

        $this->assertFalse($tabla->getColumn('sku')->isToggleable());
        $this->assertFalse($tabla->getColumn('created_at')->isToggleable());
    }

    public function test_the_selector_order_is_remembered_after_leaving_the_list(): void
    {
        $this->tresProductos();
        $this->ordenar(ListProducts::class, 'price', 'desc');

        // Volver al listado (p. ej. tras editar un producto): orden y selector a la par.
        Livewire::test(ListProducts::class)
            ->assertSet('tableSortColumn', 'price')
            ->assertSet('tableSortDirection', 'desc')
            ->assertSet('tableFilters.orden.columna', 'price')
            ->assertSet('tableFilters.orden.direccion', 'desc')
            ->assertCanSeeTableRecords([$this->a, $this->c, $this->b], inOrder: true);
    }

    public function test_a_header_sort_is_remembered_together_with_the_selector(): void
    {
        $this->tresProductos();

        // Primero el selector, después el encabezado: al volver tiene que quedar el del
        // encabezado en los dos lados, no el selector viejo.
        $this->ordenar(ListProducts::class, 'price', 'desc')->sortTable('name', 'desc');

        Livewire::test(ListProducts::class)
            ->assertSet('tableSortColumn', 'name')
            ->assertSet('tableSortDirection', 'desc')
            ->assertSet('tableFilters.orden.columna', 'name')
            ->assertSet('tableFilters.orden.direccion', 'desc')
            ->assertCanSeeTableRecords([$this->c, $this->b, $this->a], inOrder: true);
    }

    public function test_only_the_column_is_remembered_as_ascending_in_the_selector(): void
    {
        $this->tresProductos();
        $this->ordenar(ListProducts::class, 'price', null);

        Livewire::test(ListProducts::class)
            ->assertSet('tableSortDirection', 'asc')
            ->assertSet('tableFilters.orden.direccion', 'asc');
    }

    public function test_reset_is_remembered_as_the_default_order(): void
    {
        $this->tresProductos();
        $this->ordenar(ListProducts::class, 'price', 'desc')->call('resetTableFiltersForm');

        Livewire::test(ListProducts::class)
            ->assertSet('tableSortColumn', null)
            ->assertSet('tableFilters.orden.columna', null)
            ->assertDontSee('Orden: ')
            // Por defecto: más recientes primero (B hoy, C ayer, A hace 2 días).
            ->assertCanSeeTableRecords([$this->b, $this->c, $this->a], inOrder: true);
    }

    public function test_other_filters_are_remembered_with_the_order(): void
    {
        $this->tresProductos();
        $this->ordenar(ListProducts::class, 'price', 'desc')->filterTable('is_active', true);

        Livewire::test(ListProducts::class)
            ->assertSet('tableSortColumn', 'price')
            ->assertCanSeeTableRecords([$this->a, $this->c], inOrder: true)
            ->assertCanNotSeeTableRecords([$this->b]);
    }

    public function test_customers_remember_the_order_too(): void
    {
        $ana = $this->cliente(['first_name' => 'Ana', 'last_name' => 'Zeta', 'email' => 'ana@test.com'], pedidos: 2);
        $beto = $this->cliente(['first_name' => 'Beto', 'last_name' => 'Alfa', 'email' => 'beto@test.com'], pedidos: 5);

        $this->ordenar(ListCustomers::class, 'orders_count', 'desc');

        Livewire::test(ListCustomers::class)
            ->assertSet('tableFilters.orden.columna', 'orders_count')
            ->assertSee('Orden: Cantidad de pedidos (descendente)')
            ->assertCanSeeTableRecords([$beto, $ana], inOrder: true);
    }
}
