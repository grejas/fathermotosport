<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandResource;
use App\Filament\Resources\BrandResource\Pages\CreateBrand;
use App\Filament\Resources\BrandResource\Pages\EditBrand;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Brand;
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
 * Panel: ordenar los listados de productos y clientes por cada columna, y volver al
 * listado tras guardar en los recursos de catálogo.
 */
class AdminSortingAndRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());

        // El seeder puede traer productos y clientes: se parte de cero para comparar orden.
        Product::query()->forceDelete();
        User::whereHas('role', fn ($q) => $q->where('slug', 'cliente'))->forceDelete();
    }

    private function categoria(string $nombre): Category
    {
        return Category::create(['name' => $nombre, 'slug' => Str::slug($nombre).'-'.Str::random(5)]);
    }

    /** Producto con variantes controladas: el stock total es exactamente $stock. */
    private function producto(array $atributos, array $stocks = []): Product
    {
        $product = Product::factory()->create($atributos);
        $product->variants()->delete();

        foreach ($stocks as $i => $stock) {
            ProductVariant::factory()->for($product)->create(['size' => ['S', 'M', 'L'][$i], 'stock' => $stock]);
        }

        return $product;
    }

    private function cliente(array $atributos, int $pedidos = 0): User
    {
        // created_at no es asignable en masa en User: se fija aparte.
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

    // ───────────────────────── Productos ─────────────────────────

    public function test_products_are_newest_first_by_default(): void
    {
        $viejo = $this->producto(['created_at' => now()->subDays(3)]);
        $nuevo = $this->producto(['created_at' => now()]);
        $medio = $this->producto(['created_at' => now()->subDay()]);

        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$nuevo, $medio, $viejo], inOrder: true);
    }

    public function test_products_sort_by_each_column_both_ways(): void
    {
        $cascos = $this->categoria('Cascos');
        $botas = $this->categoria('Botas');

        $a = $this->producto(['name' => 'Alfa', 'sku' => 'SKU-C', 'price' => 300, 'is_active' => true,
            'category_id' => $botas->id, 'created_at' => now()->subDays(2)], [5, 5]);
        $b = $this->producto(['name' => 'Beta', 'sku' => 'SKU-A', 'price' => 100, 'is_active' => false,
            'category_id' => $cascos->id, 'created_at' => now()], [1]);
        $c = $this->producto(['name' => 'Gama', 'sku' => 'SKU-B', 'price' => 200, 'is_active' => true,
            'category_id' => $cascos->id, 'created_at' => now()->subDay()], [3, 3, 3]);

        $casos = [
            'name' => [$a, $b, $c],
            'sku' => [$b, $c, $a],
            'price' => [$b, $c, $a],
            'variants_sum_stock' => [$b, $c, $a], // stock total 1, 9, 10
            'created_at' => [$a, $c, $b],
        ];

        foreach ($casos as $columna => $ascendente) {
            Livewire::test(ListProducts::class)
                ->sortTable($columna)
                ->assertCanSeeTableRecords($ascendente, inOrder: true);
            Livewire::test(ListProducts::class)
                ->sortTable($columna, 'desc')
                ->assertCanSeeTableRecords(array_reverse($ascendente), inOrder: true);
        }

        // Categoría: Botas (A) antes que Cascos (B, C).
        Livewire::test(ListProducts::class)->sortTable('category.name')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true)
            ->assertCanSeeTableRecords([$a, $c], inOrder: true);

        // Estado: el inactivo (B) primero en ascendente, último en descendente.
        Livewire::test(ListProducts::class)->sortTable('is_active')
            ->assertCanSeeTableRecords([$b, $a], inOrder: true)
            ->assertCanSeeTableRecords([$b, $c], inOrder: true);
        Livewire::test(ListProducts::class)->sortTable('is_active', 'desc')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true)
            ->assertCanSeeTableRecords([$c, $b], inOrder: true);
    }

    public function test_a_product_without_variants_sorts_as_zero_stock(): void
    {
        $sinVariantes = $this->producto([]);
        $conStock = $this->producto([], [2]);

        Livewire::test(ListProducts::class)->sortTable('variants_sum_stock')
            ->assertCanSeeTableRecords([$sinVariantes, $conStock], inOrder: true);
    }

    // ───────────────────────── Clientes ─────────────────────────

    public function test_customers_sort_by_each_column_both_ways(): void
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
            Livewire::test(ListCustomers::class)
                ->sortTable($columna)
                ->assertCanSeeTableRecords($ascendente, inOrder: true);
            Livewire::test(ListCustomers::class)
                ->sortTable($columna, 'desc')
                ->assertCanSeeTableRecords(array_reverse($ascendente), inOrder: true);
        }
    }

    public function test_customer_search_still_works_while_sorted(): void
    {
        $ana = $this->cliente(['first_name' => 'Ana', 'last_name' => 'Zeta', 'email' => 'ana@test.com']);
        $beto = $this->cliente(['first_name' => 'Beto', 'last_name' => 'Alfa', 'email' => 'beto@test.com']);

        Livewire::test(ListCustomers::class)
            ->sortTable('orders_count', 'desc')
            ->searchTable('Ana')
            ->assertCanSeeTableRecords([$ana])
            ->assertCanNotSeeTableRecords([$beto]);
    }

    // ───────────────────────── Redirección tras guardar ─────────────────────────

    public function test_saving_a_product_goes_back_to_the_list_with_a_notification(): void
    {
        $product = $this->producto(['name' => 'Casco Original', 'category_id' => $this->categoria('Accesorios')->id], [2]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Casco Renombrado'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Producto guardado correctamente')
            ->assertRedirect(ProductResource::getUrl('index'));

        $this->assertSame('Casco Renombrado', $product->fresh()->name);
    }

    public function test_save_and_continue_keeps_the_product_open(): void
    {
        $product = $this->producto(['name' => 'Casco Original', 'category_id' => $this->categoria('Accesorios')->id], [2]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Casco Sigue'])
            ->callAction('saveAndContinue')
            ->assertHasNoFormErrors()
            ->assertNotified('Producto guardado correctamente')
            ->assertNoRedirect();

        $this->assertSame('Casco Sigue', $product->fresh()->name);
    }

    public function test_catalog_resources_go_back_to_the_list_after_saving(): void
    {
        Livewire::test(CreateBrand::class)
            ->fillForm(['name' => 'Marca Nueva', 'slug' => 'marca-nueva'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BrandResource::getUrl('index'));

        $marca = Brand::where('slug', 'marca-nueva')->firstOrFail();

        Livewire::test(EditBrand::class, ['record' => $marca->getRouteKey()])
            ->fillForm(['name' => 'Marca Editada'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(BrandResource::getUrl('index'));

        $categoria = $this->categoria('Guantes');

        Livewire::test(EditCategory::class, ['record' => $categoria->getRouteKey()])
            ->fillForm(['name' => 'Guantes Editados'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(CategoryResource::getUrl('index'));
    }
}
