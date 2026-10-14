<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use App\Services\FrontendRevalidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryRevalidationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://fathermotosport.com/api/revalidate';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        config(['services.revalidate.secret' => 'secreto', 'app.frontend_url' => 'https://fathermotosport.com']);
        $this->actingAs(User::where('email', 'admin@fathermotosport.com')->firstOrFail());
    }

    private function avisosDeCategorias(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::URL && $r['tag'] === 'categories')->count();
    }

    public function test_creating_a_category_from_the_panel_notifies_the_website(): void
    {
        Http::fake([self::URL => Http::response(['revalidated' => true])]);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Hoddie', 'slug' => 'hoddie', 'size_type' => Category::SIZE_NONE])
            ->call('create')
            ->assertHasNoFormErrors();

        Http::assertSent(fn (Request $r) => $r->url() === self::URL
            && $r['tag'] === 'categories'
            && $r->hasHeader('x-revalidate-secret', 'secreto'));
        $this->assertSame(1, $this->avisosDeCategorias());
    }

    public function test_editing_a_category_from_the_panel_notifies_the_website(): void
    {
        Http::fake([self::URL => Http::response(['revalidated' => true])]);
        $cascos = Category::where('slug', 'cascos')->firstOrFail();

        Livewire::test(EditCategory::class, ['record' => $cascos->getRouteKey()])
            ->fillForm(['name' => 'Cascos de moto'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Cascos de moto', $cascos->fresh()->name);
        $this->assertSame(1, $this->avisosDeCategorias());
    }

    public function test_activating_and_deactivating_notifies_the_website(): void
    {
        Http::fake([self::URL => Http::response(['revalidated' => true])]);
        $viseras = Category::where('slug', 'viseras')->firstOrFail();

        Livewire::test(EditCategory::class, ['record' => $viseras->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($viseras->fresh()->is_active);

        $viseras->fresh()->update(['is_active' => true]);

        $this->assertSame(2, $this->avisosDeCategorias());
    }

    public function test_saving_without_changes_does_not_notify(): void
    {
        Http::fake([self::URL => Http::response(['revalidated' => true])]);
        $cascos = Category::where('slug', 'cascos')->firstOrFail();

        $cascos->save();
        $cascos->update(['name' => 'Cascos']);

        Http::assertNothingSent();
    }

    public function test_deleting_a_category_from_the_panel_notifies_the_website(): void
    {
        Http::fake([self::URL => Http::response(['revalidated' => true])]);
        $nueva = Category::create(['name' => 'Temporal', 'slug' => 'temporal']);

        Livewire::test(ListCategories::class)
            ->callTableAction('delete', $nueva);

        $this->assertModelMissing($nueva);
        $this->assertSame(2, $this->avisosDeCategorias());
    }

    public function test_a_rejected_notification_logs_an_error_and_keeps_the_change(): void
    {
        Http::fake([self::URL => Http::response(['message' => 'Unknown tag'], 400)]);
        Log::spy();

        $categoria = Category::create(['name' => 'Hoddie', 'slug' => 'hoddie']);

        $this->assertModelExists($categoria);
        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje, $contexto) => $mensaje === 'La web rechazó la revalidación.'
            && $contexto['tag'] === 'categories'
            && $contexto['status'] === 400
            && $contexto['url'] === self::URL);
    }

    public function test_an_unreachable_website_logs_an_error_and_keeps_the_change(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        Log::spy();

        $cascos = Category::where('slug', 'cascos')->firstOrFail();
        $cascos->update(['is_active' => false]);

        $this->assertFalse($cascos->fresh()->is_active);
        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje, $contexto) => $mensaje === 'No se pudo contactar a la web para revalidar.'
            && $contexto['tag'] === 'categories'
            && str_contains($contexto['error'], 'timed out'));
    }

    public function test_missing_secret_logs_an_error_without_calling_the_website(): void
    {
        config(['services.revalidate.secret' => '']);
        Http::fake();
        Log::spy();

        Category::create(['name' => 'Hoddie', 'slug' => 'hoddie']);

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje, $contexto) => str_contains($mensaje, 'falta REVALIDATE_SECRET')
            && $contexto['tag'] === 'categories');
    }

    public function test_banners_failures_are_also_logged_as_errors(): void
    {
        Http::fake([self::URL => Http::response('', 500)]);
        Log::spy();

        app(FrontendRevalidator::class)->revalidate('banners');

        Log::shouldHaveReceived('error')->withArgs(fn ($mensaje, $contexto) => $contexto['tag'] === 'banners' && $contexto['status'] === 500);
        Log::shouldNotHaveReceived('warning');
    }
}
