<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\SizeCatalog;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tipo de stock por categoría (size_type / size_options), configurable desde el panel:
 * reemplaza la lista fija de slugs que tenía SizeCatalog.
 */
class CategorySizeTypeTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_14_100001_add_size_type_to_categories_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@fathermotosport.com')->firstOrFail();
    }

    private function categoria(string $slug, string $tipo, ?array $opciones = null, ?int $padre = null): Category
    {
        return Category::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'parent_id' => $padre,
            'size_type' => $tipo,
            'size_options' => $opciones,
        ]);
    }

    private function productoSinVariantes(string $sku, Category $categoria): Product
    {
        $product = Product::factory()->create(['sku' => $sku, 'category_id' => $categoria->id]);
        $product->variants()->delete();

        return $product;
    }

    private function crearProducto(Category $categoria, string $sku, array $tallas)
    {
        $filas = [];
        foreach ($tallas as $i => $talla) {
            $filas["fila-{$i}"] = ['size' => $talla, 'stock' => $i + 1, 'is_active' => true];
        }

        return Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => "Producto {$sku}",
                'slug' => strtolower($sku),
                'brand_id' => Brand::value('id'),
                'category_id' => $categoria->id,
                'sku' => $sku,
                'price' => 50,
            ])
            ->set('data.variants', $filas)
            ->call('create');
    }

    // ── Cada tipo ──────────────────────────────────────────────────────────

    public function test_each_stock_type_resolves_its_sizes(): void
    {
        // Letras: en el orden canónico, sin importar cómo se guardaron; ignora lo que no es talla.
        $letras = $this->categoria('hoodies', Category::SIZE_LETTERS, ['sizes' => ['XXL', 'S', 'XL', 'M', 'L', 'ZZ']]);
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL'], SizeCatalog::forCategoryId($letras->id));

        $extremos = $this->categoria('tallas-extremas', Category::SIZE_LETTERS, ['sizes' => ['5XL', 'XXXS', 'XXXL', '4XL']]);
        $this->assertSame(['XXXS', 'XXXL', '4XL', '5XL'], SizeCatalog::forCategoryId($extremos->id));

        // Números: enteros y medias tallas con punto.
        $enteros = $this->categoria('zapatillas', Category::SIZE_NUMERIC, ['from' => 38, 'to' => 41, 'step' => 1]);
        $this->assertSame(['38', '39', '40', '41'], SizeCatalog::forCategoryId($enteros->id));

        $medias = $this->categoria('botines', Category::SIZE_NUMERIC, ['from' => 36, 'to' => 38, 'step' => 0.5]);
        $this->assertSame(['36', '36.5', '37', '37.5', '38'], SizeCatalog::forCategoryId($medias->id));

        // Lista propia: en el orden escrito, sin vacíos, espacios ni repetidos.
        $propia = $this->categoria('pantalones', Category::SIZE_CUSTOM, ['sizes' => ['Única', ' 28 ', '30', '', 'Niño', '30']]);
        $this->assertSame(['Única', '28', '30', 'Niño'], SizeCatalog::forCategoryId($propia->id));

        // Solo stock, y una raíz que hereda: sin tallas.
        $this->assertSame([], SizeCatalog::forCategoryId($this->categoria('stickers', Category::SIZE_NONE)->id));
        $this->assertSame([], SizeCatalog::forCategoryId($this->categoria('llaveros', Category::SIZE_INHERIT)->id));

        // Una categoría creada sin tipo queda en 'inherit'.
        $this->assertSame(Category::SIZE_INHERIT, Category::create(['name' => 'X', 'slug' => 'x'])->refresh()->size_type);

        // all() suma las listas configuradas, sin repetir.
        $todas = SizeCatalog::all();
        foreach (['XXXS', '5XL', '36.5', '41', 'Única', 'Niño', 'XS'] as $talla) {
            $this->assertContains($talla, $todas);
        }
        $this->assertSame($todas, array_values(array_unique($todas)));
    }

    public function test_invalid_numeric_ranges_give_no_sizes(): void
    {
        $this->assertSame([], SizeCatalog::numericSizes(40, 38, 1));
        $this->assertSame([], SizeCatalog::numericSizes(36, 47, 2));
        $this->assertSame([], SizeCatalog::numericSizes(1, 500, 1));
        $this->assertSame([], SizeCatalog::numericSizes(null, 40, 1));
        $this->assertSame(['40'], SizeCatalog::numericSizes(40, 40, 1));
        $this->assertCount(SizeCatalog::MAX_NUMERIC_SIZES, SizeCatalog::numericSizes(1, 100, 1));
    }

    // ── Herencia ───────────────────────────────────────────────────────────

    public function test_subcategories_inherit_through_every_level_and_none_cuts_the_chain(): void
    {
        $ropa = $this->categoria('ropa', Category::SIZE_LETTERS, ['sizes' => ['S', 'M', 'L']]);
        $hoodies = $this->categoria('ropa-hoodies', Category::SIZE_INHERIT, null, $ropa->id);
        $zip = $this->categoria('ropa-hoodies-zip', Category::SIZE_INHERIT, null, $hoodies->id);
        $parches = $this->categoria('ropa-parches', Category::SIZE_NONE, null, $ropa->id);
        $parchesBordados = $this->categoria('ropa-parches-bordados', Category::SIZE_INHERIT, null, $parches->id);
        $ninos = $this->categoria('ropa-ninos', Category::SIZE_CUSTOM, ['sizes' => ['4', '6', '8']], $ropa->id);

        $this->assertSame(['S', 'M', 'L'], SizeCatalog::forCategoryId($hoodies->id));
        $this->assertSame(['S', 'M', 'L'], SizeCatalog::forCategoryId($zip->id));
        $this->assertSame([], SizeCatalog::forCategoryId($parches->id));
        $this->assertSame([], SizeCatalog::forCategoryId($parchesBordados->id));
        $this->assertSame(['4', '6', '8'], SizeCatalog::forCategoryId($ninos->id));

        // Cambiar el padre cambia lo heredado.
        $ropa->update(['size_options' => ['sizes' => ['M', 'XL']]]);
        $this->assertSame(['M', 'XL'], SizeCatalog::forCategoryId($zip->id));

        // Un ciclo en la cadena de padres no cuelga.
        $a = $this->categoria('ciclo-a', Category::SIZE_INHERIT);
        $b = $this->categoria('ciclo-b', Category::SIZE_INHERIT, null, $a->id);
        $a->update(['parent_id' => $b->id]);
        $this->assertSame([], SizeCatalog::forCategoryId($a->id));
    }

    // ── Panel: categoría ───────────────────────────────────────────────────

    public function test_hoodies_configured_from_the_panel_offers_its_sizes_in_the_product_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Hoodies',
                'slug' => 'hoodies',
                'size_type' => Category::SIZE_LETTERS,
                'tallas_letras' => ['XXL', 'S', 'XL', 'M', 'L'],
            ])
            ->assertSee('S, M, L, XL, XXL')
            ->call('create')
            ->assertHasNoFormErrors();

        $hoodies = Category::where('slug', 'hoodies')->firstOrFail();
        $this->assertSame(Category::SIZE_LETTERS, $hoodies->size_type);
        $this->assertSame(['sizes' => ['S', 'M', 'L', 'XL', 'XXL']], $hoodies->size_options);

        // El formulario de productos ofrece exactamente esas tallas.
        $this->crearProducto($hoodies, 'FMS-HOOD', ['S', 'XL'])->assertHasNoFormErrors();
        $variantes = Product::where('sku', 'FMS-HOOD')->firstOrFail()->variants()->orderBy('size')->get();
        $this->assertSame([['S', 'FMS-HOOD-S', 1], ['XL', 'FMS-HOOD-XL', 2]], $variantes->map(fn ($v) => [$v->size, $v->sku, $v->stock])->sortBy(0)->values()->all());

        // La edición del producto ofrece exactamente las tallas de la categoría.
        $producto = Product::where('sku', 'FMS-HOOD')->firstOrFail();
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL'], $this->opcionesDeTalla($producto));
    }

    public function test_custom_list_works_in_the_product_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Pantalones',
                'slug' => 'pantalones',
                'size_type' => Category::SIZE_CUSTOM,
                'tallas_lista' => ['Única', '28', '30', 'Niño'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $categoria = Category::where('slug', 'pantalones')->firstOrFail();
        $this->assertSame(['sizes' => ['Única', '28', '30', 'Niño']], $categoria->size_options);

        $this->crearProducto($categoria, 'FMS-PANT', ['Niño', '28'])->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing(
            ['Niño', '28'],
            Product::where('sku', 'FMS-PANT')->firstOrFail()->variants()->pluck('size')->all()
        );
        // Ofrece la lista en el orden escrito.
        $this->assertSame(['Única', '28', '30', 'Niño'], $this->opcionesDeTalla(Product::where('sku', 'FMS-PANT')->firstOrFail()));
    }

    /** Opciones del Select "Talla" de una variante del producto en el formulario de edición. */
    private function opcionesDeTalla(Product $producto): array
    {
        $variante = $producto->variants()->firstOrFail();
        $opciones = null;

        Livewire::test(EditProduct::class, ['record' => $producto->getRouteKey()])
            ->assertFormFieldExists("variants.record-{$variante->id}.size", function (Select $campo) use (&$opciones) {
                $opciones = $campo->getOptions();

                return true;
            });

        // Sin la talla actual de la variante, que siempre se agrega para no perderla.
        // (PHP convierte las claves "28" en enteros: se vuelven a texto.)
        $tallas = array_map('strval', array_keys($opciones));

        return array_values(array_filter($tallas, fn ($talla) => $talla !== $variante->size || in_array($talla, SizeCatalog::forCategoryId($producto->category_id), true)));
    }

    public function test_numeric_type_with_half_sizes_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Zapatillas',
                'slug' => 'zapatillas',
                'size_type' => Category::SIZE_NUMERIC,
                'tallas_desde' => '38',
                'tallas_hasta' => '40',
                'tallas_paso' => '0.5',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $categoria = Category::where('slug', 'zapatillas')->firstOrFail();
        $this->assertSame(['from' => 38, 'to' => 40, 'step' => 0.5], $categoria->size_options);
        $this->assertSame(['38', '38.5', '39', '39.5', '40'], SizeCatalog::forCategoryId($categoria->id));

        $this->crearProducto($categoria, 'FMS-ZAP', ['38.5'])->assertHasNoFormErrors();
        $this->assertSame('FMS-ZAP-38.5', Product::where('sku', 'FMS-ZAP')->firstOrFail()->variants()->value('sku'));
    }

    public function test_category_form_validates_each_type(): void
    {
        $this->actingAs($this->admin());
        $base = ['name' => 'Prueba', 'slug' => 'prueba'];

        Livewire::test(CreateCategory::class)
            ->fillForm($base + ['size_type' => Category::SIZE_LETTERS, 'tallas_letras' => []])
            ->call('create')
            ->assertHasFormErrors(['tallas_letras' => 'required']);

        Livewire::test(CreateCategory::class)
            ->fillForm($base + ['size_type' => Category::SIZE_NUMERIC, 'tallas_desde' => '40', 'tallas_hasta' => '38', 'tallas_paso' => '1'])
            ->call('create')
            ->assertHasFormErrors(['tallas_hasta']);

        Livewire::test(CreateCategory::class)
            ->fillForm($base + ['size_type' => Category::SIZE_NUMERIC, 'tallas_desde' => '1', 'tallas_hasta' => '500', 'tallas_paso' => '1'])
            ->call('create')
            ->assertHasFormErrors(['tallas_hasta']);

        Livewire::test(CreateCategory::class)
            ->fillForm($base + ['size_type' => Category::SIZE_CUSTOM, 'tallas_lista' => []])
            ->call('create')
            ->assertHasFormErrors(['tallas_lista' => 'required']);

        $this->assertFalse(Category::where('slug', 'prueba')->exists());

        // Solo stock no pide nada más, y no guarda opciones.
        Livewire::test(CreateCategory::class)
            ->fillForm($base + ['size_type' => Category::SIZE_NONE])
            ->call('create')
            ->assertHasNoFormErrors();
        $prueba = Category::where('slug', 'prueba')->firstOrFail();
        $this->assertSame([Category::SIZE_NONE, null], [$prueba->size_type, $prueba->size_options]);
    }

    public function test_edit_form_loads_the_saved_options_and_table_shows_the_type(): void
    {
        $this->actingAs($this->admin());
        $botas = Category::where('slug', 'botas')->firstOrFail();
        $cascos = Category::where('slug', 'cascos')->firstOrFail();

        Livewire::test(EditCategory::class, ['record' => $botas->getRouteKey()])
            ->assertFormSet(['size_type' => 'numeric', 'tallas_desde' => 36, 'tallas_hasta' => 47, 'tallas_paso' => '1']);

        Livewire::test(EditCategory::class, ['record' => $cascos->getRouteKey()])
            ->assertFormSet(['size_type' => 'letters', 'tallas_letras' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']]);

        $this->categoria('botas-enduro', Category::SIZE_INHERIT, null, $botas->id);

        Livewire::test(ListCategories::class)
            ->assertSee('Letras (XS–XXL)')
            ->assertSee('Números 36–47')
            ->assertSee('Hereda → 36–47')
            ->assertSee('Solo stock');
    }

    // ── Cambio de tipo ─────────────────────────────────────────────────────

    public function test_changing_type_or_list_keeps_existing_variants_sizes_and_stock(): void
    {
        $this->actingAs($this->admin());
        $guantes = Category::where('slug', 'guantes')->firstOrFail();
        $product = $this->productoSinVariantes('FMS-GLV', $guantes);
        ProductVariant::factory()->for($product)->create(['size' => 'XS', 'sku' => 'FMS-GLV-XS', 'stock' => 2]);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'sku' => 'FMS-GLV-M', 'stock' => 4]);
        $antes = $product->variants()->orderBy('size')->get(['id', 'size', 'sku', 'stock', 'is_active'])->toArray();

        // Quitar XS de la lista.
        Livewire::test(EditCategory::class, ['record' => $guantes->getRouteKey()])
            ->fillForm(['size_type' => Category::SIZE_LETTERS, 'tallas_letras' => ['S', 'M', 'L']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['S', 'M', 'L'], SizeCatalog::forCategoryId($guantes->id));
        $this->assertSame($antes, $product->variants()->orderBy('size')->get(['id', 'size', 'sku', 'stock', 'is_active'])->toArray());

        // La talla que salió de la lista se sigue viendo (no estándar) en el producto.
        $this->get("/admin/products/{$product->getRouteKey()}/edit")
            ->assertOk()
            ->assertSee('XS (no estándar)');

        // Pasar a otra lista y a solo stock tampoco toca nada.
        foreach ([
            ['size_type' => Category::SIZE_CUSTOM, 'tallas_lista' => ['Única']],
            ['size_type' => Category::SIZE_NONE],
        ] as $cambio) {
            Livewire::test(EditCategory::class, ['record' => $guantes->getRouteKey()])
                ->fillForm($cambio)
                ->call('save')
                ->assertHasNoFormErrors();
            $this->assertSame($antes, $product->variants()->orderBy('size')->get(['id', 'size', 'sku', 'stock', 'is_active'])->toArray());
        }

        // Al cambiar de tipo, las opciones del tipo anterior no quedan guardadas.
        $this->assertNull($guantes->refresh()->size_options);

        // Y el producto se sigue pudiendo guardar con sus tallas viejas.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($antes, $product->variants()->orderBy('size')->get(['id', 'size', 'sku', 'stock', 'is_active'])->toArray());
    }

    // ── Migración ──────────────────────────────────────────────────────────

    public function test_migration_on_a_database_with_data_including_a_missing_slug(): void
    {
        $migracion = require base_path(self::MIGRACION);

        // Base como la de producción antes de desplegar: sin las columnas, con categorías
        // y productos, y uno de los slugs del paso de datos ausente.
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('categories', 'size_type'));
        $this->assertFalse(Schema::hasColumn('categories', 'size_options'));

        DB::table('categories')->where('slug', 'guantes')->update(['slug' => 'guantes-moto']);
        DB::table('categories')->insert(['name' => 'Cascos Niño', 'slug' => 'cascos-nino', 'parent_id' => DB::table('categories')->where('slug', 'cascos')->value('id'), 'is_active' => true, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()]);
        $antes = DB::table('categories')->orderBy('id')->get(['id', 'name', 'slug', 'parent_id', 'is_active', 'sort_order'])->toArray();
        $productos = DB::table('products')->count();
        $variantes = DB::table('product_variants')->orderBy('id')->get(['id', 'size', 'stock'])->toArray();

        $migracion->up();

        $tipos = DB::table('categories')->pluck('size_type', 'slug')->all();
        $this->assertSame('letters', $tipos['cascos']);
        $this->assertSame('letters', $tipos['chamarras']);
        $this->assertSame('numeric', $tipos['botas']);
        $this->assertSame('inherit', $tipos['guantes-moto']);
        $this->assertSame('inherit', $tipos['cascos-nino']);
        $this->assertSame('inherit', $tipos['viseras']);
        $this->assertSame('inherit', $tipos['accesorios']);
        $this->assertSame(['from' => 36, 'to' => 47, 'step' => 1], json_decode(DB::table('categories')->where('slug', 'botas')->value('size_options'), true));
        $this->assertNull(DB::table('categories')->where('slug', 'viseras')->value('size_options'));

        // Se comporta como la lista fija: la subcategoría de cascos hereda sus letras.
        $id = fn (string $slug) => DB::table('categories')->where('slug', $slug)->value('id');
        $this->assertSame(['XS', 'S', 'M', 'L', 'XL', 'XXL'], SizeCatalog::forCategoryId($id('cascos-nino')));
        $this->assertSame(array_map('strval', range(36, 47)), SizeCatalog::forCategoryId($id('botas')));
        $this->assertSame([], SizeCatalog::forCategoryId($id('viseras')));

        // No toca nada más.
        $this->assertEquals($antes, DB::table('categories')->orderBy('id')->get(['id', 'name', 'slug', 'parent_id', 'is_active', 'sort_order'])->toArray());
        $this->assertSame($productos, DB::table('products')->count());
        $this->assertEquals($variantes, DB::table('product_variants')->orderBy('id')->get(['id', 'size', 'stock'])->toArray());

        // Volver a correrla no pisa lo configurado en el panel ni falla.
        Category::where('slug', 'cascos')->update(['size_type' => 'custom', 'size_options' => json_encode(['sizes' => ['Única']])]);
        $migracion->up();
        $this->assertSame('custom', DB::table('categories')->where('slug', 'cascos')->value('size_type'));

        // down() → up(): se pierden solo las columnas nuevas; las categorías siguen igual.
        $migracion->down();
        $migracion->up();
        $this->assertEquals($antes, DB::table('categories')->orderBy('id')->get(['id', 'name', 'slug', 'parent_id', 'is_active', 'sort_order'])->toArray());
        $this->assertSame('letters', DB::table('categories')->where('slug', 'cascos')->value('size_type'));
    }

    public function test_seeder_does_not_overwrite_the_type_configured_in_the_panel(): void
    {
        Category::where('slug', 'cascos')->update(['size_type' => Category::SIZE_NONE, 'size_options' => null]);

        $this->seed(CategorySeeder::class);

        $this->assertSame(Category::SIZE_NONE, Category::where('slug', 'cascos')->value('size_type'));
        $this->assertSame(Category::SIZE_NUMERIC, Category::where('slug', 'botas')->value('size_type'));
    }
}
