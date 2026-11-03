<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Support\SizeCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            static::seccionDeDescuento(),
        ])->columns(2);
    }

    /**
     * Descuento de categoría: un porcentaje sobre el precio de lista de todos sus
     * productos (y los de sus subcategorías), salvo los excluidos. Se calcula en vivo
     * (PrecioFinal): no cambia los precios guardados ni el descuento propio de cada
     * producto, y si choca con él gana el que deja el precio más bajo.
     */
    protected static function seccionDeDescuento(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Descuento de categoría')
            ->description('Un porcentaje de descuento para todos los productos de esta categoría y de sus subcategorías. No cambia los precios guardados: para quitarlo, apaga el interruptor. Si un producto ya tiene su propio descuento, se aplica solo el que deje el precio más bajo (no se suman). Las horas son de Bolivia (UTC-4).')
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Porcentaje')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->minValue(Category::DESCUENTO_MIN)
                    ->maxValue(Category::DESCUENTO_MAX)
                    ->rule('decimal:0,2')
                    ->suffix('%')
                    ->helperText('De 0,5 % a 90 %, con hasta dos decimales (por ejemplo 7,5).')
                    ->required(fn (Get $get): bool => (bool) $get('discount_active'))
                    ->live(onBlur: true),
                Forms\Components\Toggle::make('discount_active')
                    ->label('Descuento activo')
                    ->helperText('Al pasar la fecha de fin se apaga solo. Sin fechas, queda activo hasta que lo apagues.')
                    ->default(false)
                    ->inline(false)
                    ->live(),
                Forms\Components\DateTimePicker::make('discount_starts_at')
                    ->label('Inicio (opcional)')
                    ->seconds(false)
                    ->native(false)
                    ->timezone(FlashPromoResource::TIMEZONE)
                    ->displayFormat('d/m/Y H:i')
                    ->live(onBlur: true),
                Forms\Components\DateTimePicker::make('discount_ends_at')
                    ->label('Fin (opcional)')
                    ->seconds(false)
                    ->native(false)
                    ->timezone(FlashPromoResource::TIMEZONE)
                    ->displayFormat('d/m/Y H:i')
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        $inicio = $get('discount_starts_at');
                        $fin = $get('discount_ends_at');
                        if (filled($fin) && filled($inicio) && strtotime((string) $fin) <= strtotime((string) $inicio)) {
                            $fail('El fin tiene que ser posterior al inicio.');
                        }
                    })
                    ->live(onBlur: true),
                Forms\Components\Select::make('productosExcluidos')
                    ->label('Productos excluidos')
                    ->helperText('No reciben el descuento de esta categoría; conservan solo su propio descuento, si lo tienen.')
                    ->relationship(
                        'productosExcluidos',
                        'name',
                        modifyQueryUsing: fn (Builder $query, ?Category $record) => $query
                            ->whereIn('products.category_id', $record ? Category::idsConDescendientes($record->id) : [])
                            ->orderBy('products.name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Product $product): string => "{$product->name} ({$product->sku})")
                    ->multiple()
                    ->searchable(['name', 'sku'])
                    ->preload()
                    ->visibleOn('edit')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('notify_customers')
                    ->label('Avisar a los clientes por correo')
                    ->helperText('Encenderlo no envía nada: habilita el botón "Avisar por correo" arriba de esta página, con vista previa, prueba y confirmación. Cada descuento se avisa una sola vez.')
                    ->default(false)
                    ->inline(false)
                    ->visibleOn('edit')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('descuento_resumen')
                    ->label('Cómo queda')
                    ->content(fn (Get $get, ?Category $record): string => static::resumenDeDescuento($get, $record))
                    ->columnSpanFull(),
            ]);
    }

    /** Estado y un ejemplo con precio 100, para la vista previa del formulario. */
    protected static function resumenDeDescuento(Get $get, ?Category $record): string
    {
        $porcentaje = (float) $get('discount_percent');

        $vista = new Category([
            'discount_percent' => $porcentaje,
            'discount_active' => (bool) $get('discount_active'),
            'discount_starts_at' => filled($get('discount_starts_at'))
                ? Carbon::parse($get('discount_starts_at'), FlashPromoResource::TIMEZONE) : null,
            'discount_ends_at' => filled($get('discount_ends_at'))
                ? Carbon::parse($get('discount_ends_at'), FlashPromoResource::TIMEZONE) : null,
        ]);

        $estado = match ($vista->estadoDelDescuento()) {
            Category::DESCUENTO_APAGADO => 'Apagado: no se aplica.',
            Category::DESCUENTO_PROGRAMADO => 'Programado: empieza el '.$vista->discount_starts_at->timezone(FlashPromoResource::TIMEZONE)->format('d/m/Y H:i').'.',
            Category::DESCUENTO_VENCIDO => 'Vencido: la fecha de fin ya pasó, no se aplica.',
            default => 'Vigente ahora'.($vista->discount_ends_at ? ', hasta el '.$vista->discount_ends_at->timezone(FlashPromoResource::TIMEZONE)->format('d/m/Y H:i') : ', sin fecha de fin').'.',
        };

        if ($porcentaje <= 0) {
            return $estado;
        }

        $ejemplo = number_format(round(100 * (1 - $porcentaje / 100), 2), 2, ',', '.');
        $excluidos = $record ? count((array) $get('productosExcluidos')) : 0;

        return $estado.' Ejemplo: un producto de $100,00 queda en $'.$ejemplo.'.'
            .($excluidos ? " Excluidos: {$excluidos}." : '');
    }

    /** "-15 % vigente", "-7,5 % programado"…, para la tabla. */
    public static function describirDescuento(Category $category): ?string
    {
        if ($category->discount_percent === null || (float) $category->discount_percent <= 0) {
            return null;
        }

        $porcentaje = rtrim(rtrim(number_format((float) $category->discount_percent, 2, ',', ''), '0'), ',');

        return "-{$porcentaje} % ".$category->estadoDelDescuento();
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
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Descuento')
                    ->formatStateUsing(fn (Category $record) => static::describirDescuento($record))
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (Category $record) => match ($record->estadoDelDescuento()) {
                        Category::DESCUENTO_VIGENTE => 'success',
                        Category::DESCUENTO_PROGRAMADO => 'info',
                        Category::DESCUENTO_VENCIDO => 'warning',
                        default => 'gray',
                    }),
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
