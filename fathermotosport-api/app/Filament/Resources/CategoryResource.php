<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Support\SizeCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('parent_id')
                ->label('Categoría padre')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Ninguna (categoría raíz)'),
            Forms\Components\TextInput::make('icon')
                ->label('Ícono')
                ->placeholder('shield-half'),
            Forms\Components\FileUpload::make('image_url')
                ->label('Imagen')
                ->image()
                ->directory('categories')
                ->visibility('public'),
            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),
            static::seccionDeTallas(),
        ])->columns(2);
    }

    /**
     * Tipo de stock de la categoría: decide si los productos llevan talla y cuáles se
     * ofrecen en la sección Variantes del producto (vía SizeCatalog).
     *
     * Los campos de cada tipo (tallas_*) no son columnas: las páginas de crear y editar
     * los pasan a size_options y de vuelta (tallasAlFormulario / tallasDelFormulario).
     */
    protected static function seccionDeTallas(): Forms\Components\Section
    {
        $esTipo = fn (string $tipo) => fn (Get $get) => $get('size_type') === $tipo;

        return Forms\Components\Section::make('Stock y tallas')
            ->description('Define si los productos de esta categoría llevan talla y cuáles se ofrecen al cargar su stock. Cambiarlo no toca las variantes ya cargadas: conservan su talla y su stock.')
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                Forms\Components\Select::make('size_type')
                    ->label('Tipo de stock')
                    ->options(Category::SIZE_TYPES)
                    ->default(Category::SIZE_INHERIT)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->helperText(fn (Get $get) => $get('size_type') === Category::SIZE_INHERIT
                        ? 'Usa el tipo de la categoría padre. En una categoría raíz equivale a "Solo stock".'
                        : null)
                    ->columnSpanFull(),

                Forms\Components\CheckboxList::make('tallas_letras')
                    ->label('Tallas que se ofrecen')
                    ->options(array_combine(SizeCatalog::LETTER_ORDER, SizeCatalog::LETTER_ORDER))
                    ->columns(['default' => 3, 'sm' => 6, 'lg' => 11])
                    ->bulkToggleable()
                    ->required()
                    ->live()
                    ->visible($esTipo(Category::SIZE_LETTERS))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('tallas_desde')
                    ->label('Desde')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->required()
                    ->live(onBlur: true)
                    ->visible($esTipo(Category::SIZE_NUMERIC)),
                Forms\Components\TextInput::make('tallas_hasta')
                    ->label('Hasta')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->required()
                    ->gte('tallas_desde')
                    ->live(onBlur: true)
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        if (is_numeric($get('tallas_desde')) && is_numeric($value)
                            && SizeCatalog::numericSizes($get('tallas_desde'), $value, $get('tallas_paso')) === []
                            && (float) $get('tallas_desde') <= (float) $value) {
                            $fail('El rango genera más de '.SizeCatalog::MAX_NUMERIC_SIZES.' tallas.');
                        }
                    })
                    ->visible($esTipo(Category::SIZE_NUMERIC)),
                Forms\Components\Select::make('tallas_paso')
                    ->label('Paso')
                    ->options(['1' => '1 (tallas enteras)', '0.5' => '0,5 (medias tallas)'])
                    ->default('1')
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->visible($esTipo(Category::SIZE_NUMERIC)),

                Forms\Components\TagsInput::make('tallas_lista')
                    ->label('Opciones')
                    ->placeholder('Escribe una opción y pulsa Enter')
                    ->helperText('Por ejemplo: Única, 28, 30, Niño. Se ofrecen en este orden; arrástralas para reordenarlas.')
                    ->reorderable()
                    ->required()
                    ->nestedRecursiveRules(['string', 'max:50'])
                    ->live()
                    ->visible($esTipo(Category::SIZE_CUSTOM))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('tallas_resultado')
                    ->label('Tallas que verá el formulario de productos')
                    ->content(fn (Get $get) => static::resumenDeTallas(
                        SizeCatalog::sizesOf($get('size_type'), static::tallasDelFormulario([
                            'size_type' => $get('size_type'),
                            'tallas_letras' => $get('tallas_letras'),
                            'tallas_desde' => $get('tallas_desde'),
                            'tallas_hasta' => $get('tallas_hasta'),
                            'tallas_paso' => $get('tallas_paso'),
                            'tallas_lista' => $get('tallas_lista'),
                        ])['size_options']),
                        $get('size_type'),
                        $get('parent_id'),
                    ))
                    ->columnSpanFull(),
            ]);
    }

    /** size_options del registro → campos del formulario (al abrir la edición). */
    public static function tallasAlFormulario(array $data): array
    {
        $opciones = $data['size_options'] ?? [];
        $tipo = $data['size_type'] ?? Category::SIZE_INHERIT;

        $data['tallas_letras'] = $tipo === Category::SIZE_LETTERS ? array_values((array) ($opciones['sizes'] ?? [])) : [];
        $data['tallas_lista'] = $tipo === Category::SIZE_CUSTOM ? array_values((array) ($opciones['sizes'] ?? [])) : [];
        $data['tallas_desde'] = $tipo === Category::SIZE_NUMERIC ? ($opciones['from'] ?? null) : null;
        $data['tallas_hasta'] = $tipo === Category::SIZE_NUMERIC ? ($opciones['to'] ?? null) : null;
        $data['tallas_paso'] = $tipo === Category::SIZE_NUMERIC ? (string) ($opciones['step'] ?? '1') : '1';

        return $data;
    }

    /**
     * Campos del formulario → size_options según el tipo elegido (al guardar). Solo se
     * guardan las opciones del tipo actual: al cambiar de tipo, las del anterior se descartan.
     */
    public static function tallasDelFormulario(array $data): array
    {
        $tipo = $data['size_type'] ?? Category::SIZE_INHERIT;

        $data['size_options'] = match ($tipo) {
            Category::SIZE_LETTERS => ['sizes' => array_values(array_intersect(SizeCatalog::LETTER_ORDER, (array) ($data['tallas_letras'] ?? [])))],
            Category::SIZE_NUMERIC => [
                'from' => static::numero($data['tallas_desde'] ?? null),
                'to' => static::numero($data['tallas_hasta'] ?? null),
                'step' => static::numero($data['tallas_paso'] ?? 1),
            ],
            Category::SIZE_CUSTOM => ['sizes' => SizeCatalog::cleanList((array) ($data['tallas_lista'] ?? []))],
            default => null,
        };

        unset($data['tallas_letras'], $data['tallas_desde'], $data['tallas_hasta'], $data['tallas_paso'], $data['tallas_lista']);

        return $data;
    }

    /** "36" → 36, "36.5" → 36.5; así el JSON guarda números y no textos. */
    private static function numero(mixed $valor): int|float|null
    {
        if (! is_numeric($valor)) {
            return null;
        }

        return (float) $valor == (int) $valor ? (int) $valor : (float) $valor;
    }

    /** Texto de la vista previa del formulario. */
    protected static function resumenDeTallas(array $tallas, ?string $tipo, mixed $parentId): string
    {
        if ($tipo === Category::SIZE_INHERIT) {
            $heredadas = SizeCatalog::forCategoryId($parentId);

            return $heredadas === []
                ? 'Ninguna: solo stock (sin categoría padre con tallas).'
                : 'Heredadas del padre: '.implode(', ', $heredadas);
        }

        if ($tipo === Category::SIZE_NONE) {
            return 'Ninguna: solo stock.';
        }

        return $tallas === [] ? 'Todavía ninguna: completa los campos de arriba.' : implode(', ', $tallas);
    }

    /** Tipo de stock en la tabla, ej. "Letras (S–XXL)", "Números 36–47", "Hereda → Letras (XS–XXL)". */
    public static function describirTipo(Category $category): string
    {
        if ($category->size_type === Category::SIZE_INHERIT) {
            if (! $category->parent_id) {
                return 'Solo stock';
            }

            $tallas = SizeCatalog::forCategoryId($category->parent_id);

            return 'Hereda → '.($tallas === [] ? 'Solo stock' : static::rango($tallas));
        }

        $tallas = SizeCatalog::sizesOf($category->size_type, $category->size_options);

        return match ($category->size_type) {
            Category::SIZE_NONE => 'Solo stock',
            Category::SIZE_LETTERS => 'Letras ('.static::rango($tallas).')',
            Category::SIZE_NUMERIC => 'Números '.static::rango($tallas)
                .((float) ($category->size_options['step'] ?? 1) === 0.5 ? ' (medias tallas)' : ''),
            Category::SIZE_CUSTOM => 'Lista propia ('.count($tallas).')',
            default => (string) $category->size_type,
        };
    }

    private static function rango(array $tallas): string
    {
        if ($tallas === []) {
            return 'sin tallas';
        }

        return count($tallas) === 1 ? $tallas[0] : $tallas[0].'–'.end($tallas);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label('Ícono'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Padre')
                    ->badge()
                    ->placeholder('Raíz'),
                Tables\Columns\TextColumn::make('size_type')
                    ->label('Tipo de stock')
                    ->formatStateUsing(fn (Category $record) => static::describirTipo($record))
                    ->badge()
                    ->color(fn (Category $record) => $record->size_type === Category::SIZE_INHERIT ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
