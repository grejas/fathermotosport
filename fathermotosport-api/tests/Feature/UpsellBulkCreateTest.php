<?php

namespace Tests\Feature;

use App\Filament\Resources\UpsellRuleResource\Pages\ListUpsellRules;
use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Creación masiva de reglas de venta cruzada desde el listado del panel: cada
 * disparador × cada oferta.
 *
 * Lo que importa: que no se dupliquen reglas, que ningún producto se ofrezca a sí
 * mismo, y que un lote que falla no deje la mitad creada.
 */
class UpsellBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function categoria(string $nombre, ?Category $padre = null): Category
    {
        return Category::create([
            'name' => $nombre,
            'slug' => Str::slug($nombre).'-'.Str::random(5),
            'parent_id' => $padre?->id,
        ]);
    }

    private function producto(string $nombre, ?Category $categoria = null, bool $activo = true): Product
    {
        return Product::factory()->create([
            'name' => $nombre,
            'category_id' => $categoria?->id ?? $this->categoria('Varios')->id,
            'is_active' => $activo,
        ]);
    }

    private function regla(Product $disparador, Product $ofrecido, array $extra = []): UpsellRule
    {
        return UpsellRule::create(array_merge([
            'trigger_product_id' => $disparador->id,
            'offer_product_id' => $ofrecido->id,
            'discount_percent' => 30,
            'priority' => 1,
            'is_active' => true,
        ], $extra));
    }

    private function datos(array $datos): array
    {
        return array_merge([
            'category_id' => null,
            'product_ids' => [],
            'offer_category_id' => null,
            'offer_product_ids' => [],
            'discount_percent' => 15,
            'priority' => 5,
            'sobrescribir' => false,
            'descuentos' => [],
        ], $datos);
    }

    private function lote(array $datos)
    {
        return Livewire::test(ListUpsellRules::class)->callAction('crearEnLote', $this->datos($datos));
    }

    private function resumen(array $datos)
    {
        return Livewire::test(ListUpsellRules::class)
            ->mountAction('crearEnLote')
            ->setActionData($this->datos($datos));
    }

    private function reglaDe(Product $disparador, Product $ofrecido): ?UpsellRule
    {
        return UpsellRule::where('trigger_product_id', $disparador->id)
            ->where('offer_product_id', $ofrecido->id)
            ->first();
    }

    public function test_creates_one_rule_per_trigger_and_offer_combination(): void
    {
        $a = $this->producto('Casco A');
        $b = $this->producto('Casco B');
        $rinonera = $this->producto('Riñonera');
        $guantes = $this->producto('Guantes');

        $this->lote([
            'product_ids' => [$a->id, $b->id],
            'offer_product_ids' => [$rinonera->id, $guantes->id],
        ])->assertHasNoActionErrors();

        $this->assertSame(4, UpsellRule::count());
        foreach ([$a, $b] as $disparador) {
            foreach ([$rinonera, $guantes] as $ofrecido) {
                $regla = $this->reglaDe($disparador, $ofrecido);
                $this->assertNotNull($regla);
                $this->assertSame(15, $regla->discount_percent);
                $this->assertSame(5, $regla->priority);
                $this->assertTrue($regla->is_active);
            }
        }

        Notification::assertNotified('4 creadas');
    }

    public function test_categories_on_both_sides_include_subcategories_and_skip_inactive_products(): void
    {
        $cascos = $this->categoria('Cascos');
        $integrales = $this->categoria('Integrales', $cascos);
        $a = $this->producto('Casco A', $cascos);
        $b = $this->producto('Casco B', $integrales);
        $this->producto('Casco viejo', $cascos, activo: false);

        $accesorios = $this->categoria('Accesorios');
        $rinonera = $this->producto('Riñonera', $accesorios);
        $this->producto('Accesorio viejo', $accesorios, activo: false);

        $this->lote([
            'category_id' => $cascos->id,
            'offer_category_id' => $accesorios->id,
        ])->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing(
            [$a->id.'|'.$rinonera->id, $b->id.'|'.$rinonera->id],
            UpsellRule::get()->map(fn ($r) => $r->trigger_product_id.'|'.$r->offer_product_id)->all()
        );
    }

    public function test_offers_are_merged_without_duplicates_and_never_offered_to_themselves(): void
    {
        $cascos = $this->categoria('Cascos');
        $a = $this->producto('Casco A', $cascos);
        $b = $this->producto('Casco B', $cascos);
        $rinonera = $this->producto('Riñonera');

        // Casco A está de los dos lados y también repetido entre las ofertas.
        $this->lote([
            'product_ids' => [$a->id, $b->id],
            'offer_category_id' => $cascos->id,
            'offer_product_ids' => [$a->id, $rinonera->id],
        ])->assertHasNoActionErrors();

        // Ofertas: A, B, Riñonera. Pares: A→B, A→Riñonera, B→A, B→Riñonera (sin A→A ni B→B).
        $this->assertSame(4, UpsellRule::count());
        $this->assertNull($this->reglaDe($a, $a));
        $this->assertNull($this->reglaDe($b, $b));
        $this->assertNotNull($this->reglaDe($a, $b));
        $this->assertNotNull($this->reglaDe($b, $a));
    }

    public function test_a_per_product_discount_overrides_the_global_one(): void
    {
        $a = $this->producto('Casco A');
        $rinonera = $this->producto('Riñonera');
        $guantes = $this->producto('Guantes');

        $this->lote([
            'product_ids' => [$a->id],
            'offer_product_ids' => [$rinonera->id, $guantes->id],
            'discount_percent' => 15,
            'descuentos' => [['offer_product_id' => $guantes->id, 'discount_percent' => 40]],
        ])->assertHasNoActionErrors();

        $this->assertSame(15, $this->reglaDe($a, $rinonera)->discount_percent);
        $this->assertSame(40, $this->reglaDe($a, $guantes)->discount_percent);
    }

    public function test_a_per_product_discount_must_also_be_between_1_and_90(): void
    {
        $a = $this->producto('Casco A');
        $rinonera = $this->producto('Riñonera');

        $this->lote([
            'product_ids' => [$a->id],
            'offer_product_ids' => [$rinonera->id],
            'descuentos' => [['offer_product_id' => $rinonera->id, 'discount_percent' => 95]],
        ])
            // La clave del error lleva el id del ítem del repeater, que es aleatorio.
            ->assertHasActionErrors();

        $this->assertSame(0, UpsellRule::count());
    }

    public function test_existing_rules_are_skipped_and_named_in_the_notification(): void
    {
        $a = $this->producto('Casco A');
        $b = $this->producto('Casco B');
        $rinonera = $this->producto('Riñonera');
        $previa = $this->regla($a, $rinonera);

        $this->lote([
            'product_ids' => [$a->id, $b->id],
            'offer_product_ids' => [$rinonera->id],
        ])->assertHasNoActionErrors();

        $this->assertSame(2, UpsellRule::count());
        // La que ya existía queda intacta.
        $this->assertSame(30, $previa->fresh()->discount_percent);
        $this->assertSame(1, $previa->fresh()->priority);

        Notification::assertNotified(
            Notification::make()
                ->title('1 creada, 1 omitida (ya existían)')
                ->body('Omitidas: Casco A → Riñonera.')
                ->success()
                ->persistent()
        );
    }

    public function test_overwrite_updates_only_the_discount_using_the_per_product_one(): void
    {
        $a = $this->producto('Casco A');
        $rinonera = $this->producto('Riñonera');
        $guantes = $this->producto('Guantes');
        $r1 = $this->regla($a, $rinonera, ['is_active' => false]);
        $r2 = $this->regla($a, $guantes);

        $this->lote([
            'product_ids' => [$a->id],
            'offer_product_ids' => [$rinonera->id, $guantes->id],
            'discount_percent' => 15,
            'sobrescribir' => true,
            'descuentos' => [['offer_product_id' => $guantes->id, 'discount_percent' => 40]],
        ])->assertHasNoActionErrors();

        $this->assertSame(15, $r1->fresh()->discount_percent);
        $this->assertSame(40, $r2->fresh()->discount_percent);
        // Prioridad y estado son decisiones aparte: no se tocan.
        $this->assertSame(1, $r1->fresh()->priority);
        $this->assertFalse($r1->fresh()->is_active);

        Notification::assertNotified('0 creadas, 2 actualizadas (ya existían)');
    }

    public function test_more_than_100_rules_is_rejected_and_nothing_is_created(): void
    {
        // 11 × 10 = 110 combinaciones.
        $disparadores = Product::factory()->count(11)->create(['is_active' => true]);
        $ofertas = Product::factory()->count(10)->create(['is_active' => true]);

        $this->lote([
            'product_ids' => $disparadores->pluck('id')->all(),
            'offer_product_ids' => $ofertas->pluck('id')->all(),
        ]);

        $this->assertSame(0, UpsellRule::count());
        Notification::assertNotified('No se creó ninguna regla');
    }

    public function test_exactly_100_rules_is_allowed(): void
    {
        $disparadores = Product::factory()->count(10)->create(['is_active' => true]);
        $ofertas = Product::factory()->count(10)->create(['is_active' => true]);

        $this->lote([
            'product_ids' => $disparadores->pluck('id')->all(),
            'offer_product_ids' => $ofertas->pluck('id')->all(),
        ])->assertHasNoActionErrors();

        $this->assertSame(100, UpsellRule::count());
    }

    public function test_without_valid_combinations_nothing_is_created(): void
    {
        $rinonera = $this->producto('Riñonera');

        // El único disparador es la única oferta: no queda ningún par.
        $this->lote(['product_ids' => [$rinonera->id], 'offer_product_ids' => [$rinonera->id]]);

        $this->assertSame(0, UpsellRule::count());
        Notification::assertNotified('No se creó ninguna regla');
    }

    public function test_the_global_discount_must_be_between_1_and_90(): void
    {
        $a = $this->producto('Casco A');
        $rinonera = $this->producto('Riñonera');

        $this->lote(['product_ids' => [$a->id], 'offer_product_ids' => [$rinonera->id], 'discount_percent' => 95])
            ->assertHasActionErrors(['discount_percent']);
        $this->lote(['product_ids' => [$a->id], 'offer_product_ids' => [$rinonera->id], 'discount_percent' => 0])
            ->assertHasActionErrors(['discount_percent']);

        $this->assertSame(0, UpsellRule::count());
    }

    public function test_the_live_counter_shows_the_formula_and_existing_rules(): void
    {
        $a = $this->producto('Casco A');
        $b = $this->producto('Casco B');
        $rinonera = $this->producto('Riñonera');
        $guantes = $this->producto('Guantes');
        $this->regla($a, $rinonera);

        $this->resumen([
            'product_ids' => [$a->id, $b->id],
            'offer_product_ids' => [$rinonera->id, $guantes->id],
        ])->assertSee('Se crearán 3 reglas (2 disparadores × 2 ofertas) · 1 ya existe y se omitirá.');
    }

    public function test_the_live_counter_warns_when_over_the_cap(): void
    {
        $disparadores = Product::factory()->count(11)->create(['is_active' => true]);
        $ofertas = Product::factory()->count(10)->create(['is_active' => true]);

        $this->resumen([
            'product_ids' => $disparadores->pluck('id')->all(),
            'offer_product_ids' => $ofertas->pluck('id')->all(),
        ])->assertSee('Serían 110 reglas (11 disparadores × 10 ofertas): el tope es 100 por vez.');
    }

    public function test_the_modal_warns_when_a_trigger_would_have_more_than_three_offers(): void
    {
        $casco = $this->producto('Casco AGV');
        $otro = $this->producto('Casco B');
        // Casco AGV ya tiene 2 ofertas activas; una inactiva no cuenta.
        $this->regla($casco, $this->producto('Visera'));
        $this->regla($casco, $this->producto('Balaclava'));
        $this->regla($casco, $this->producto('Pinlock'), ['is_active' => false]);

        $nuevas = [$this->producto('Riñonera')->id, $this->producto('Guantes')->id];

        // AGV: 2 + 2 = 4 → aviso. Casco B: 0 + 2 = 2 → sin aviso.
        $this->resumen(['product_ids' => [$casco->id, $otro->id], 'offer_product_ids' => $nuevas])
            ->assertSee('Nota: 1 disparador quedará con más de 3 ofertas activas.')
            ->assertSee('Casco AGV.')
            ->assertDontSee('Casco B.');
    }

    public function test_no_warning_when_every_trigger_stays_within_three_offers(): void
    {
        $casco = $this->producto('Casco AGV');
        $this->regla($casco, $this->producto('Visera'));

        $this->resumen([
            'product_ids' => [$casco->id],
            'offer_product_ids' => [$this->producto('Riñonera')->id, $this->producto('Guantes')->id],
        ])->assertDontSee('Nota:');
    }
}
