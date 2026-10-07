<?php

namespace Tests\Feature;

use App\Filament\Resources\UpsellRuleResource\Pages\ListUpsellRules;
use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\UpsellService;
use Database\Seeders\DatabaseSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * Disparadores y ofertas distintos: el lote tiene exactamente $d × $o combinaciones.
     *
     * @return array{product_ids: array<int, string>, offer_product_ids: array<int, string>}
     */
    private function seleccion(int $disparadores, int $ofertas): array
    {
        return [
            'product_ids' => Product::factory()->count($disparadores)->create(['is_active' => true])->pluck('id')->all(),
            'offer_product_ids' => Product::factory()->count($ofertas)->create(['is_active' => true])->pluck('id')->all(),
        ];
    }

    /** Cuántas consultas hace $accion. */
    private function consultas(callable $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $accion();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    public function test_110_rules_now_fit_in_one_batch(): void
    {
        $this->lote($this->seleccion(11, 10))->assertHasNoActionErrors();

        $this->assertSame(110, UpsellRule::count());
        Notification::assertNotified('110 creadas');
    }

    public function test_more_than_1000_rules_is_rejected_and_nothing_is_created(): void
    {
        // 77 × 13 = 1001 combinaciones.
        $this->lote($this->seleccion(77, 13));

        $this->assertSame(0, UpsellRule::count());
        Notification::assertNotified(
            Notification::make()
                ->title('No se creó ninguna regla')
                ->body('Son 1001 reglas y el tope es 1000 por vez. Achicá la selección y repetí en otra tanda.')
                ->danger()
        );
    }

    public function test_exactly_1000_rules_is_allowed(): void
    {
        $this->lote($this->seleccion(40, 25))->assertHasNoActionErrors();

        $this->assertSame(1000, UpsellRule::count());
        $this->assertSame(1000, UpsellRule::where('discount_percent', 15)->where('priority', 5)->where('is_active', true)->count());
        $this->assertNotNull(UpsellRule::first()->created_at);
        Notification::assertNotified('1000 creadas');
    }

    public function test_creating_and_overwriting_do_not_run_one_query_per_rule(): void
    {
        $upsell = app(UpsellService::class);
        $pares = fn (array $s) => $upsell->paresDelLote(collect($s['product_ids']), collect($s['offer_product_ids']));

        $chico = $pares($this->seleccion(2, 2));
        $grande = $pares($this->seleccion(40, 25));
        $descuentos = fn ($p) => [$p->first()['offer'] => 40];

        $crearChico = $this->consultas(fn () => $upsell->crearReglasEnLote($chico, 15, 5));
        $crearGrande = $this->consultas(fn () => $upsell->crearReglasEnLote($grande, 15, 5));

        // Las 1000 entran en 2 INSERT de 500: una consulta más que el lote de 4, no 996.
        $this->assertSame(1000 + 4, UpsellRule::count());
        $this->assertLessThanOrEqual($crearChico + 1, $crearGrande);

        // Sobrescribir las 1000 ya existentes, con un descuento propio para una oferta:
        // una actualización por descuento distinto (y por bloque de 500), no por regla.
        $pisarChico = $this->consultas(fn () => $upsell->crearReglasEnLote($chico, 20, 5, true, $descuentos($chico)));
        $pisarGrande = $this->consultas(fn () => $upsell->crearReglasEnLote($grande, 20, 5, true, $descuentos($grande)));

        $this->assertLessThanOrEqual($pisarChico + 2, $pisarGrande);

        // De las 1000: las 40 de la oferta con descuento propio, al 40%; las 960 restantes, al 20%.
        $delLote = UpsellRule::whereIn('offer_product_id', $grande->pluck('offer')->unique());
        $this->assertSame(40, (clone $delLote)->where('discount_percent', 40)->count());
        $this->assertSame(960, (clone $delLote)->where('discount_percent', 20)->count());
        // Prioridad y estado no se tocan al sobrescribir.
        $this->assertSame(1000, (clone $delLote)->where('priority', 5)->where('is_active', true)->count());
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
        $this->resumen($this->seleccion(77, 13))
            ->assertSee('Serían 1001 reglas (77 disparadores × 13 ofertas): el tope es 1000 por vez.');
    }

    public function test_the_live_counter_handles_1000_rules_without_one_query_per_rule(): void
    {
        $chico = $this->seleccion(2, 2);
        $grande = $this->seleccion(40, 25);

        // Toda la selección en una sola actualización, como la manda el multiselect del
        // navegador. (setActionData manda una por cada id: un render por id.)
        $pagina = Livewire::test(ListUpsellRules::class)->mountAction('crearEnLote');
        $elegir = fn (array $seleccion) => $pagina->set('mountedActionsData.0', $this->datos($seleccion));

        $consultasChico = $this->consultas(fn () => $elegir($chico));
        $pagina->assertSee('Se crearán 4 reglas (2 disparadores × 2 ofertas).');

        $consultasGrande = $this->consultas(fn () => $elegir($grande));
        $pagina->assertSee('Se crearán 1000 reglas (40 disparadores × 25 ofertas).');

        $this->assertSame($consultasChico, $consultasGrande);
    }

    public function test_the_selectors_offer_every_active_product_not_just_50(): void
    {
        // Filament muestra 50 opciones por defecto; con más productos, el resto no
        // aparecía en la lista ni en la búsqueda.
        Product::factory()->count(60)->create(['is_active' => true]);
        $activos = Product::where('is_active', true)->count();
        $this->assertGreaterThan(50, $activos);

        Livewire::test(ListUpsellRules::class)
            ->mountAction('crearEnLote')
            ->assertSeeHtml('optionsLimit: '.$activos);
    }

    public function test_the_modal_warns_when_a_trigger_would_have_more_than_five_offers(): void
    {
        $casco = $this->producto('Casco AGV');
        $otro = $this->producto('Casco B');
        // Casco AGV ya tiene 4 ofertas activas; una inactiva no cuenta.
        $this->regla($casco, $this->producto('Visera'));
        $this->regla($casco, $this->producto('Balaclava'));
        $this->regla($casco, $this->producto('Mangas'));
        $this->regla($casco, $this->producto('Intercomunicador'));
        $this->regla($casco, $this->producto('Pinlock'), ['is_active' => false]);

        $nuevas = [$this->producto('Riñonera')->id, $this->producto('Guantes')->id];

        // AGV: 4 + 2 = 6 → aviso. Casco B: 0 + 2 = 2 → sin aviso.
        $this->resumen(['product_ids' => [$casco->id, $otro->id], 'offer_product_ids' => $nuevas])
            ->assertSee('Nota: 1 disparador quedará con más de 5 ofertas activas.')
            ->assertSee('Casco AGV.')
            ->assertDontSee('Casco B.');
    }

    public function test_no_warning_when_every_trigger_stays_within_five_offers(): void
    {
        $casco = $this->producto('Casco AGV');
        // 3 + 2 = 5: justo el tope, sin aviso.
        $this->regla($casco, $this->producto('Visera'));
        $this->regla($casco, $this->producto('Balaclava'));
        $this->regla($casco, $this->producto('Mangas'));

        $this->resumen([
            'product_ids' => [$casco->id],
            'offer_product_ids' => [$this->producto('Riñonera')->id, $this->producto('Guantes')->id],
        ])->assertDontSee('Nota:');
    }
}
