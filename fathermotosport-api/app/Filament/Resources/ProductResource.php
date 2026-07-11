<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Información principal')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            $set('slug', Str::slug($state ?? ''));

                            // Autogenerar el SKU a partir del nombre solo si el admin
                            // no lo personalizó (sigue vacío o con patrón autogenerado).
                            if (filled($state) && static::skuEsAutogenerado($get('sku'))) {
                                $set('sku', static::generarSkuProducto($get('brand_id'), $state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('brand_id')
                        ->label('Marca')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        // Al elegir/cambiar la marca, refrescar el SKU con el slug de
                        // la marca (FMS-{MARCA}-XXXXXX), respetando ediciones manuales.
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if (static::skuEsAutogenerado($get('sku'))) {
                                $set('sku', static::generarSkuProducto($state, $get('name')));
                            }
                        }),
                    Forms\Components\Select::make('category_id')
                        ->label('Categoría')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->default(fn () => 'FMS-'.Str::upper(Str::random(8)))
                        ->unique(ignoreRecord: true)
                        ->helperText('Se autogenera; podés editarlo o regenerarlo.')
                        ->suffixAction(
                            Action::make('regenerate_sku')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip('Regenerar SKU')
                                ->action(fn (Get $get, Set $set) => $set(
                                    'sku',
                                    static::generarSkuProducto($get('brand_id'), $get('name'))
                                )),
                        ),
                    Forms\Components\Toggle::make('is_featured')->label('Destacado'),
                    Forms\Components\Toggle::make('is_new')->label('Nuevo'),
                    Forms\Components\Toggle::make('is_popular')->label('Popular'),
                    Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
                ]),

            Forms\Components\Section::make('Precios e inventario')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Precio')
                        ->numeric()
                        ->prefix('$')
                        ->required(),
                    Forms\Components\TextInput::make('sale_price')
                        ->label('Precio de oferta')
                        ->numeric()
                        ->prefix('$'),
                    Forms\Components\TextInput::make('cost')
                        ->label('Costo')
                        ->numeric()
                        ->prefix('$')
                        ->hint('Solo visible para admin')
                        ->visible(fn () => auth()->user()?->isAdmin()),
                    Forms\Components\TextInput::make('minimum_stock')
                        ->label('Stock mínimo')
                        ->numeric()
                        ->default(3),
                ]),

            Forms\Components\Section::make('Descripción')
                ->schema([
                    Forms\Components\RichEditor::make('description')
                        ->label('Descripción completa')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('certification')
                        ->label('Certificación')
                        ->placeholder('ECE 22.06, DOT'),
                    Forms\Components\TextInput::make('weight')
                        ->label('Peso')
                        ->numeric()
                        ->suffix('kg'),
                    Forms\Components\KeyValue::make('specs')
                        ->label('Especificaciones')
                        ->keyLabel('Atributo')
                        ->valueLabel('Valor')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Imágenes')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship('images')
                        ->label('Galería')
                        ->schema([
                            Forms\Components\FileUpload::make('url')
                                ->label('Imagen')
                                ->image()
                                ->disk('public')
                                ->directory('products')
                                ->visibility('public')
                                ->imagePreviewHeight('120')
                                ->required(),
                            Forms\Components\Toggle::make('is_primary')
                                ->label('Principal')
                                ->helperText('Marca solo una como principal.'),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Orden')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(3)
                        ->orderColumn('sort_order')
                        ->defaultItems(0)
                        ->addActionLabel('+ Agregar imagen')
                        ->reorderableWithButtons()
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Modelo 3D')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Group::make()
                        // Solo se crea/actualiza el registro model3d cuando hay un GLB.
                        // Sin esta condición, guardar un producto sin 3D intentaría
                        // insertar una fila vacía y violaría el NOT NULL de file_glb_url.
                        ->relationship('model3d', condition: fn (?array $state): bool => filled($state['file_glb_url'] ?? null))
                        ->schema([
                            Forms\Components\FileUpload::make('file_glb_url')
                                ->label('Archivo GLB')
                                ->disk('public')
                                ->directory('models')
                                ->acceptedFileTypes(['model/gltf-binary', 'application/octet-stream'])
                                ->visibility('public'),
                            Forms\Components\FileUpload::make('file_draco_url')
                                ->label('GLB Draco (comprimido)')
                                ->disk('public')
                                ->directory('models')
                                ->visibility('public'),
                            Forms\Components\FileUpload::make('preview_url')
                                ->label('Preview WebP')
                                ->image()
                                ->disk('public')
                                ->directory('models/previews')
                                ->visibility('public'),
                            Forms\Components\TextInput::make('file_size_kb')
                                ->label('Tamaño (KB)')
                                ->numeric(),
                            Forms\Components\TextInput::make('version')
                                ->label('Versión')
                                ->default('1.0'),
                        ])
                        ->columns(2),
                ]),

            Forms\Components\Section::make('Variantes')
                ->schema([
                    // relationship('variants') es CRÍTICO: carga las variantes existentes
                    // al editar y las persiste al guardar.
                    Forms\Components\Repeater::make('variants')
                        ->relationship('variants')
                        ->label('Variantes del producto')
                        ->schema([
                            Forms\Components\TextInput::make('color')
                                ->label('Color')
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                                    'sku',
                                    static::generarSkuVariante($get('../../sku'), $get('size'), $get('color'))
                                )),
                            Forms\Components\TextInput::make('size')
                                ->label('Talla')
                                ->placeholder('Ej: M, L, XL, 57-58, Único...')
                                ->nullable()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                                    'sku',
                                    static::generarSkuVariante($get('../../sku'), $get('size'), $get('color'))
                                )),
                            Forms\Components\Select::make('finish')
                                ->label('Acabado')
                                ->options([
                                    'Mate' => 'Mate', 'Brillante' => 'Brillante',
                                    'Carbono' => 'Carbono', 'Cromado' => 'Cromado',
                                ])
                                ->nullable(),
                            Forms\Components\TextInput::make('sku')
                                ->label('SKU Variante')
                                ->required()
                                ->default(fn () => 'VAR-'.Str::upper(Str::random(6)))
                                ->distinct()
                                ->unique(table: 'product_variants', column: 'sku', ignoreRecord: true)
                                ->suffixAction(
                                    Action::make('regen_var_sku')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Regenerar')
                                        ->action(fn (Get $get, Set $set) => $set(
                                            'sku',
                                            static::generarSkuVariante($get('../../sku'), $get('size'), $get('color'))
                                        )),
                                ),
                            Forms\Components\TextInput::make('stock')
                                ->label('Stock')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->default(0),
                            Forms\Components\TextInput::make('price')
                                ->label('Precio')
                                ->numeric()
                                ->nullable()
                                ->prefix('$')
                                ->helperText('Vacío = precio del producto'),
                            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->addActionLabel('+ Agregar variante')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['sku'] ?? 'Nueva variante'),
                ]),
        ]);
    }

    /**
     * Genera el SKU del producto con formato FMS-{MARCA|INICIALES}-{RANDOM6}.
     * Usa el slug de la marca si está seleccionada; si no, las iniciales del
     * nombre; y como último recurso un token aleatorio.
     */
    protected static function generarSkuProducto($brandId = null, ?string $name = null): string
    {
        $token = null;

        if ($brandId && $slug = Brand::whereKey($brandId)->value('slug')) {
            $token = Str::upper(Str::of($slug)->replace('-', ''));
        } elseif (filled($name)) {
            $palabras = preg_split('/\s+/', trim(Str::upper($name)));
            $token = collect($palabras)->take(3)
                ->map(fn ($w) => Str::substr($w, 0, 2))
                ->implode('');
        }

        $token = $token ?: Str::upper(Str::random(3));

        return 'FMS-'.$token.'-'.Str::upper(Str::random(6));
    }

    /**
     * Genera el SKU de una variante: {SKU_PRODUCTO}-{TALLA}-{INICIAL_COLOR}.
     * Si falta el SKU del producto usa un fallback aleatorio.
     */
    protected static function generarSkuVariante(?string $productSku, ?string $size, ?string $color): string
    {
        $base = filled($productSku) ? $productSku : ('VAR-'.Str::upper(Str::random(6)));

        $partes = [$base];
        if (filled($size)) {
            $partes[] = Str::upper($size);
        }
        if (filled($color)) {
            $partes[] = Str::upper(Str::substr(trim($color), 0, 1));
        }

        return implode('-', $partes);
    }

    /**
     * Indica si un SKU está vacío o sigue un patrón autogenerado (FMS-… / VAR-…),
     * de modo que se pueda sobrescribir sin pisar una edición manual del admin.
     */
    protected static function skuEsAutogenerado(?string $sku): bool
    {
        if (blank($sku)) {
            return true;
        }

        return (bool) preg_match('/^(FMS|VAR)-[A-Z0-9]+(-[A-Z0-9]+)*$/', $sku);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primary_image')
                    ->label('Imagen')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('variants_sum_stock')
                    ->label('Stock')
                    ->sum('variants', 'stock')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Categoría'),
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->label('Marca'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Destacado'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->label('Duplicar')
                    ->excludeAttributes(['slug', 'sku'])
                    ->beforeReplicaSaved(function (Product $replica): void {
                        $replica->name = $replica->name.' (copia)';
                        $replica->slug = Str::slug($replica->name).'-'.Str::random(4);
                        $replica->sku = $replica->sku.'-'.Str::upper(Str::random(4));
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activar')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                    Tables\Actions\BulkAction::make('destacar')
                        ->label('Marcar destacado')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each->update(['is_featured' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager loading para evitar N+1 en el listado (marca, categoría e
        // imágenes que usa la columna de imagen principal).
        return parent::getEloquentQuery()
            ->with(['brand:id,name', 'category:id,name', 'images'])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // ─── Permisos: Administrador y Empleado gestionan productos por completo ───
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
