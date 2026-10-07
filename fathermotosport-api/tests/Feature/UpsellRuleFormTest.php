<?php

namespace Tests\Feature;

use App\Filament\Resources\UpsellRuleResource\Pages\CreateUpsellRule;
use App\Filament\Resources\UpsellRuleResource\Pages\EditUpsellRule;
use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formulario de regla individual del panel: un disparador y varios productos
 * ofrecidos, una regla por producto. Lo que importa: no duplicar, decir qué se omitió,
 * y que una regla repetida sea un aviso en el formulario y no el error del índice único.
 */
class UpsellRuleFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function producto(string $nombre, float $precio = 100): Product
    {
        return Product::factory()->create(['name' => $nombre, 'price' => $precio, 'sale_price' => null, 'is_active' => true]);
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

    private function formulario(array $datos)
    {
        return Livewire::test(CreateUpsellRule::class)->fillForm(array_merge([
            'trigger_tipo' => 'producto',
            'offer_tipo' => 'producto',
            'discount_percent' => 20,
            'priority' => 0,
            'is_active' => true,
        ], $datos));
    }

    public function test_it_creates_one_rule_per_offered_product_with_its_own_discounts(): void
    {
        $casco = $this->producto('Casco');
        $mangas = $this->producto('Mangas');
        $balaclava = $this->producto('Balaclava');

        $this->formulario([
            'trigger_product_id' => $casco->id,
            'offer_product_ids' => [$mangas->id, $balaclava->id],
            'discount_percent' => 15,
            'descuentos' => [['offer_product_id' => $balaclava->id, 'discount_percent' => 40]],
        ])->call('create')->assertHasNoFormErrors();

        $reglas = UpsellRule::where('trigger_product_id', $casco->id)->get()->keyBy('offer_product_id');
        $this->assertCount(2, $reglas);
        $this->assertSame(15, $reglas[$mangas->id]->discount_percent);
        $this->assertSame(40, $reglas[$balaclava->id]->discount_percent);
        $this->assertNull($reglas[$mangas->id]->offer_category_id);
    }

    public function test_existing_rules_are_skipped_and_reported(): void
    {
        $casco = $this->producto('Casco');
        $mangas = $this->producto('Mangas');
        $balaclava = $this->producto('Balaclava');
        $this->regla($casco, $mangas, ['discount_percent' => 30]);

        $this->formulario([
            'trigger_product_id' => $casco->id,
            'offer_product_ids' => [$mangas->id, $balaclava->id],
        ])
            ->assertSee('Ya tienen regla con este disparador y se omitirán: Mangas.')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, UpsellRule::where('trigger_product_id', $casco->id)->count());
        // La existente no se tocó.
        $this->assertSame(30, UpsellRule::where('offer_product_id', $mangas->id)->value('discount_percent'));

        Notification::assertNotified(
            Notification::make()
                ->title('1 regla creada')
                ->body('Omitidas porque ya existían: Mangas.')
                ->warning()
                ->persistent()
        );
    }

    public function test_a_rule_that_already_exists_is_a_friendly_form_error_not_a_crash(): void
    {
        $casco = $this->producto('Casco');
        $mangas = $this->producto('Mangas');
        $this->regla($casco, $mangas);

        $this->formulario([
            'trigger_product_id' => $casco->id,
            'offer_product_ids' => [$mangas->id],
        ])
            ->assertSee('Ya existe una regla con este disparador para Mangas.')
            ->call('create')
            ->assertHasFormErrors(['offer_product_ids']);

        $this->assertSame(1, UpsellRule::count());
    }

    public function test_a_repeated_category_rule_is_also_a_friendly_error(): void
    {
        $cascos = Category::where('slug', 'cascos')->firstOrFail();
        $accesorios = Category::where('slug', 'accesorios')->firstOrFail();
        UpsellRule::create(['trigger_category_id' => $cascos->id, 'offer_category_id' => $accesorios->id, 'discount_percent' => 10]);

        $this->formulario([
            'trigger_tipo' => 'categoria',
            'trigger_category_id' => $cascos->id,
            'offer_tipo' => 'categoria',
            'offer_category_id' => $accesorios->id,
        ])->call('create')->assertHasFormErrors(['offer_category_id']);

        $this->assertSame(1, UpsellRule::count());
    }

    public function test_the_trigger_is_not_offered_and_options_are_not_capped_at_50(): void
    {
        Product::factory()->count(60)->create(['is_active' => true]);
        $casco = $this->producto('Casco');
        $activos = Product::where('is_active', true)->count();

        $pagina = $this->formulario(['trigger_product_id' => $casco->id]);

        // Todos los activos menos el disparador, sin el tope de 50 de Filament.
        $pagina->assertSeeHtml('optionsLimit: '.($activos - 1));

        // Aunque se fuerce, el servidor tampoco lo acepta.
        $pagina->set('data.offer_product_ids', [$casco->id])
            ->call('create')
            ->assertHasFormErrors(['offer_product_ids']);
        $this->assertSame(0, UpsellRule::count());
    }

    public function test_it_warns_when_the_trigger_would_have_more_than_five_offers(): void
    {
        $casco = $this->producto('Casco');
        foreach (['A', 'B', 'C', 'D'] as $nombre) {
            $this->regla($casco, $this->producto($nombre));
        }
        $nuevas = [$this->producto('E')->id];

        // 4 + 1 = 5: justo el tope.
        $this->formulario(['trigger_product_id' => $casco->id, 'offer_product_ids' => $nuevas])
            ->assertDontSee('quedará con');

        $nuevas[] = $this->producto('F')->id;

        $this->formulario(['trigger_product_id' => $casco->id, 'offer_product_ids' => $nuevas])
            ->assertSee('Nota: este disparador quedará con 6 ofertas activas.');
    }

    public function test_editing_keeps_the_rule_and_adds_one_rule_per_new_product(): void
    {
        $casco = $this->producto('Casco');
        $mangas = $this->producto('Mangas');
        $balaclava = $this->producto('Balaclava');
        $regla = $this->regla($casco, $mangas, ['discount_percent' => 30]);

        Livewire::test(EditUpsellRule::class, ['record' => $regla->getRouteKey()])
            ->assertFormSet(['offer_product_ids' => [$mangas->id], 'offer_tipo' => 'producto'])
            ->fillForm(['offer_product_ids' => [$mangas->id, $balaclava->id], 'discount_percent' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($mangas->id, $regla->fresh()->offer_product_id);
        $this->assertSame(25, $regla->fresh()->discount_percent);
        $this->assertTrue(UpsellRule::where('trigger_product_id', $casco->id)->where('offer_product_id', $balaclava->id)->exists());
    }

    public function test_editing_into_an_existing_combination_is_a_friendly_error(): void
    {
        $casco = $this->producto('Casco');
        $mangas = $this->producto('Mangas');
        $balaclava = $this->producto('Balaclava');
        $regla = $this->regla($casco, $mangas);
        $this->regla($casco, $balaclava);

        Livewire::test(EditUpsellRule::class, ['record' => $regla->getRouteKey()])
            ->fillForm(['offer_product_ids' => [$balaclava->id]])
            ->call('save')
            ->assertHasFormErrors(['offer_product_ids']);

        $this->assertSame($mangas->id, $regla->fresh()->offer_product_id);
    }

    public function test_switching_a_side_to_category_clears_the_product(): void
    {
        $casco = $this->producto('Casco');
        $regla = $this->regla($casco, $this->producto('Mangas'));
        $accesorios = Category::where('slug', 'accesorios')->firstOrFail();

        Livewire::test(EditUpsellRule::class, ['record' => $regla->getRouteKey()])
            ->fillForm(['offer_tipo' => 'categoria'])
            ->fillForm(['offer_category_id' => $accesorios->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $regla->refresh();
        $this->assertNull($regla->offer_product_id);
        $this->assertSame($accesorios->id, $regla->offer_category_id);
    }
}
