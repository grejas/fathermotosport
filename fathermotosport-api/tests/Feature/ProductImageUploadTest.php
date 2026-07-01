<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function admin(): User
    {
        $role = Role::where('slug', 'administrador')->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'first_name' => 'Admin',
            'last_name' => 'Upload',
            'email' => 'admin-upload@test.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_filament_file_upload_persists_relative_path_and_file(): void
    {
        Storage::fake('public');

        $brand = Brand::create(['name' => 'AGV', 'slug' => 'agv', 'is_active' => true]);
        $category = Category::create(['name' => 'Cascos', 'slug' => 'cascos', 'is_active' => true, 'sort_order' => 1]);

        $file = UploadedFile::fake()->image('casco.jpg', 600, 600);

        Livewire::actingAs($this->admin())
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Casco Test Upload',
                'slug' => 'casco-test-upload',
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'sku' => 'UP-001',
                'price' => 150,
                'description' => 'Casco de prueba',
                'images' => [
                    ['url' => [$file], 'is_primary' => true, 'sort_order' => 0],
                ],
                'variants' => [
                    ['sku' => 'UP-001-M', 'size' => 'M', 'stock' => 5, 'is_active' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = ProductImage::first();

        $this->assertNotNull($image, 'No se creó el registro ProductImage');

        // La columna debe guardar una RUTA RELATIVA (no una URL completa).
        $raw = $image->getRawOriginal('url');
        $this->assertStringStartsWith('products/', $raw, "url debe ser relativa, se obtuvo: {$raw}");
        $this->assertStringNotContainsString('http', $raw);

        // El archivo debe existir físicamente en el disco público.
        Storage::disk('public')->assertExists($raw);
    }
}
