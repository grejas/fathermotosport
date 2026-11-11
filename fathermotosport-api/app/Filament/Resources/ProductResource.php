<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Support\FiltroDeOrden;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\SizeCatalog;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    /** La galería acepta imágenes (GIF incluido) y cualquier video. */
    public const TIPOS_DE_GALERIA = ['image/*', 'video/*', 'application/mp4'];

    /**
     * Para el chequeo previo del navegador: algunos no informan el tipo de estos
     * formatos y FilePond los rechazaría antes de subirlos (igual que en banners).
     */
    private const MIME_POR_EXTENSION = [
        'mkv' => 'video/x-matroska',
        'ogv' => 'video/ogg',
        'm4v' => 'video/x-m4v',
        'wmv' => 'video/x-ms-wmv',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
    ];

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
                        ->live(onBlur: true)
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
                        ->required()
                        // live: las opciones de "Talla" en las variantes dependen de la categoría.
                        ->live(),
                    // Un producto = un color. Si el modelo viene en otro color, es otro producto.
                    Forms\Components\TextInput::make('color')
                        ->label('Color')
                        ->placeholder('Ej: Azul Negro')
                        ->maxLength(100)
                        ->helperText('Informativo. Otro color del mismo modelo = otro producto (podés usar "Duplicar").'),
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

            Forms\Components\Section::make('Imágenes y videos')
                ->description('Arrastra los elementos para ordenarlos: el primero es el que se ve al abrir el producto, y puede ser un video.')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship('images')
                        ->label('Galería')
                        ->schema([
                            // Solo aparece cuando el producto tiene colores en sus variantes: la
                            // foto o el video se muestra únicamente al elegir ese color.
                            Forms\Components\Select::make('color')
                                ->label('Color de esta foto')
                                ->helperText('Vacío = se muestra con todos los colores.')
                                ->options(fn (Get $get): array => self::coloresDeVariantes($get('../../variants')))
                                ->placeholder('Todos los colores')
                                ->native(false)
                                ->visible(fn (Get $get): bool => self::coloresDeVariantes($get('../../variants')) !== [])
                                ->columnSpanFull(),
                            Forms\Components\FileUpload::make('url')
                                ->label('Imagen, GIF o video')
                                ->acceptedFileTypes(self::TIPOS_DE_GALERIA)
                                ->mimeTypeMap(self::MIME_POR_EXTENSION)
                                ->rule(static fn (): Closure => self::reglaDePesoDeGaleria())
                                ->helperText('MP4 H.264 recomendado. Si no se reproduce, conviértelo a MP4 con HandBrake. Los GIF pesan mucho: mejor MP4. Videos y GIF: hasta 50 MB.')
                                ->disk('public')
                                ->directory('products')
                                ->visibility('public')
                                ->imagePreviewHeight('120')
                                ->required()
                                // Con el archivo elegido se muestran los campos de su tipo.
                                ->live()
                                // Nombre único por archivo (uniqid + nombre original) para
                                // descartar colisiones de nombre entre filas del Repeater.
                                // Los videos van a su propia carpeta.
                                ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
                                    $carpeta = self::tipoDelArchivoSubido($file) === ProductImage::VIDEO ? 'products/videos' : 'products';
                                    $filename = uniqid().'_'.$file->getClientOriginalName();
                                    $file->storeAs($carpeta, $filename, 'public');

                                    return $carpeta.'/'.$filename;
                                }),
                            Forms\Components\FileUpload::make('poster_url')
                                ->label('Poster del video (opcional)')
                                ->helperText('La imagen que se ve antes de que arranque el video. Sin poster se ve el primer fotograma.')
                                ->image()
                                ->disk('public')
                                ->directory('products/posters')
                                ->visibility('public')
                                ->imagePreviewHeight('120')
                                ->visible(fn (Get $get): bool => self::tipoDelEstado($get('url')) === ProductImage::VIDEO),
                            Forms\Components\Toggle::make('with_sound')
                                ->label('Permitir sonido')
                                ->helperText('El video arranca siempre en silencio; con esto aparece el botón para activar el sonido.')
                                ->visible(fn (Get $get): bool => self::tipoDelEstado($get('url')) === ProductImage::VIDEO),
                            Forms\Components\Toggle::make('is_primary')
                                ->label('Principal')
                                ->helperText('La imagen de las tarjetas, el carrito y los correos. Marca solo una. Si no marcas ninguna, se usa la primera imagen.')
                                ->visible(fn (Get $get): bool => in_array(self::tipoDelEstado($get('url')), [null, ProductImage::IMAGEN], true)),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): string => self::etiquetaDeElemento($state))
                        ->rule(static fn (): Closure => self::reglaDeUnaPrincipal())
                        ->orderColumn('sort_order')
                        ->defaultItems(0)
                        ->maxItems(20)
                        ->addActionLabel('+ Agregar imagen, GIF o video')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->extraItemActions([self::accionPonerAlPrincipio()])
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Modelo 3D')
                ->collapsible()
                ->collapsed()
                ->schema([
                    // Campo directo del producto (no pertenece a la relación model3d):
                    // si está completo, la web muestra este spin 360° en vez del
                    // visor 3D o la galería de fotos.
                    Forms\Components\TextInput::make('spin_url')
                        ->label('URL del Spin 360° (Sirv)')
                        ->placeholder('https://mycompany.sirv.com/spin.html?...')
                        ->url()
                        ->nullable()
                        ->helperText('Si se completa, reemplaza al visor 3D y a la galería de fotos en la página del producto.'),
                    Forms\Components\Group::make()
                        // Solo se crea/actualiza el registro model3d cuando hay un GLB.
                        // Sin esta condición, guardar un producto sin 3D intentaría
                        // insertar una fila vacía y violaría el NOT NULL de file_glb_url.
                        ->relationship('model3d', condition: fn (?array $state): bool => filled($state['file_glb_url'] ?? null))
                        ->schema([
                            Forms\Components\TextInput::make('file_glb_url')
                                ->label('URL del GLB')
                                ->placeholder('https://api.fathermotosport.com/storage/models/archivo.glb')
                                ->url()
                                ->nullable(),
                            Forms\Components\TextInput::make('file_draco_url')
                                ->label('URL GLB Draco (comprimido)')
                                ->placeholder('https://api.fathermotosport.com/storage/models/archivo_draco.glb')
                                ->url()
                                ->nullable(),
                            Forms\Components\TextInput::make('preview_url')
                                ->label('URL Preview WebP')
                                ->url()
                                ->nullable(),
                            Forms\Components\TextInput::make('file_size_kb')
                                ->label('Tamaño (KB)')
                                ->numeric(),
                            Forms\Components\TextInput::make('version')
                                ->label('Versión')
                                ->default('1.0'),
                        ])
                        ->columns(2),
                ]),

            Forms\Components\Section::make('Visores disponibles')
                ->collapsible()
                ->schema([
                    Forms\Components\CheckboxList::make('visorColors')
                        ->relationship('visorColors', 'name')
                        ->label('Visores disponibles')
                        ->columns(3),
                ]),

            Forms\Components\Section::make('Variantes')
                ->schema([
                    // relationship('variants') es CRÍTICO: carga las variantes existentes
                    // al editar y las persiste al guardar.
                    // Cada fila es Talla + Stock + Activa. El SKU se genera al guardar
                    // ({SKU_PRODUCTO}-{TALLA}). El precio es siempre el del producto; el
                    // acabado ya no se edita acá y conserva su valor en la BD.
                    Forms\Components\Repeater::make('variants')
                        ->relationship('variants')
                        ->label('Variantes del producto')
                        ->schema([
                            // Solo para productos con varios colores elegibles (guantes en rojo y en
                            // negro en el mismo enlace): se escribe el mismo nombre en todas las
                            // tallas de ese color. En el resto de productos se deja vacío.
                            Forms\Components\TextInput::make('color')
                                ->label('Color (opcional)')
                                ->placeholder('Ej. Rojo / Blanco / Negro')
                                ->maxLength(40)
                                ->datalist(fn (Get $get): array => array_values(self::coloresDeVariantes($get('../../variants'))))
                                ->helperText('Solo si el producto se vende en varios colores.'),
                            // Las opciones salen de SizeCatalog según la categoría del producto.
                            // Si la categoría no usa talla, el campo se oculta (size = NULL),
                            // salvo que la variante ya tenga una talla guardada.
                            Forms\Components\Select::make('size')
                                ->label('Talla')
                                ->options(fn (Get $get, ?string $state) => static::opcionesTalla($get('../../category_id'), $state))
                                ->visible(fn (Get $get, ?string $state) => filled($state)
                                    || SizeCatalog::forCategoryId($get('../../category_id')) !== [])
                                ->required(fn (Get $get) => SizeCatalog::forCategoryId($get('../../category_id')) !== [])
                                ->placeholder('Elegí una talla'),
                            Forms\Components\TextInput::make('stock')
                                ->label('Stock')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->default(0),
                            // Permite ocultar una talla puntual (ej. XXL descontinuada) sin tocar las demás.
                            Forms\Components\Toggle::make('is_active')
                                ->label('Activa')
                                ->default(true)
                                ->inline(false),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->addActionLabel('+ Agregar talla')
                        ->reorderable(false)
                        ->rule(static fn (): Closure => self::reglaDeVarianteUnica())
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                            $data['sku'] = static::skuVarianteUnico($get('sku'), $data['size'] ?? null, null, $data['color'] ?? null);

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data, ProductVariant $record, Get $get): array {
                            // Al guardar, toda variante existente cuyo SKU no siga el patrón
                            // vigente {SKU_PRODUCTO}-{TALLA} se regenera (cambie o no la talla,
                            // y también si cambió el SKU del producto).
                            $size = array_key_exists('size', $data) ? $data['size'] : $record->size;
                            $color = array_key_exists('color', $data) ? $data['color'] : $record->color;

                            if (filled($get('sku')) && ! ProductVariant::skuMatchesPattern($record->sku, $get('sku'), $size, $color)) {
                                $data['sku'] = static::skuVarianteUnico($get('sku'), $size, $record->id, $color);
                            }

                            return $data;
                        }),
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
     * Genera el SKU de una variante: {SKU_PRODUCTO}-{TALLA} (o solo {SKU_PRODUCTO}
     * si no hay talla). Si falta el SKU del producto usa un fallback aleatorio.
     */
    protected static function generarSkuVariante(?string $productSku, ?string $size, ?string $color = null): string
    {
        $base = filled($productSku) ? $productSku : ('VAR-'.Str::upper(Str::random(6)));

        return ProductVariant::skuFor($base, $size, $color);
    }

    /**
     * SKU de variante que no choca con otro de product_variants (agrega -2, -3…).
     * $ignoreId excluye a la propia variante al regenerar el SKU de una existente.
     */
    protected static function skuVarianteUnico(?string $productSku, ?string $size, ?string $ignoreId = null, ?string $color = null): string
    {
        $base = static::generarSkuVariante($productSku, $size, $color);
        $sku = $base;
        $n = 2;

        while (ProductVariant::where('sku', $sku)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $sku = $base.'-'.$n++;
        }

        return $sku;
    }

    /**
     * Opciones del Select de talla. Si la variante ya tiene una talla fuera de la lista
     * (datos viejos, ej. "M L XL"), se agrega marcada como no estándar para no perderla.
     */
    protected static function opcionesTalla(int|string|null $categoryId, ?string $actual): array
    {
        $tallas = SizeCatalog::forCategoryId($categoryId);
        $opciones = array_combine($tallas, $tallas);

        if (filled($actual) && ! in_array($actual, $tallas, true)) {
            $opciones = [$actual => $actual.' (no estándar)'] + $opciones;
        }

        return $opciones;
    }

    /** image, gif o video de un archivo recién subido (por su tipo y, si no alcanza, su nombre). */
    private static function tipoDelArchivoSubido(TemporaryUploadedFile $file): string
    {
        return ProductImage::tipoPorMime(rescue(fn () => $file->getMimeType(), null, false))
            ?? ProductImage::tipoPorExtension($file->getClientOriginalName());
    }

    /**
     * El tipo del elemento según el estado del campo de archivo (un archivo recién subido
     * o la ruta ya guardada). Null mientras no haya archivo.
     */
    public static function tipoDelEstado(mixed $estado): ?string
    {
        $archivo = is_array($estado) ? Arr::first($estado) : $estado;

        return match (true) {
            $archivo instanceof TemporaryUploadedFile => self::tipoDelArchivoSubido($archivo),
            is_string($archivo) && trim($archivo) !== '' => ProductImage::tipoPorExtension($archivo),
            default => null,
        };
    }

    /** El título de cada elemento plegado: su tipo, y si es la principal. */
    private static function etiquetaDeElemento(array $state): string
    {
        $tipo = self::tipoDelEstado($state['url'] ?? null);

        return match ($tipo) {
            ProductImage::VIDEO => 'Video',
            ProductImage::GIF => 'GIF',
            ProductImage::IMAGEN => ($state['is_primary'] ?? false) ? 'Imagen (principal)' : 'Imagen',
            default => 'Nuevo elemento',
        };
    }

    /**
     * Videos y GIF: hasta ProductImage::MAX_VIDEO_GIF_KB. Las imágenes no tienen tope
     * propio (manda el de Livewire). Se valida cada archivo recién subido.
     */
    private static function reglaDePesoDeGaleria(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof TemporaryUploadedFile) {
                return;
            }

            $tipo = self::tipoDelArchivoSubido($value);
            if ($tipo !== ProductImage::IMAGEN && $value->getSize() > ProductImage::MAX_VIDEO_GIF_KB * 1024) {
                $fail('Los videos y GIF pueden pesar hasta '.intdiv(ProductImage::MAX_VIDEO_GIF_KB, 1024).' MB.');
            }
        };
    }

    /** Como máximo una imagen marcada "Principal" (en un video o GIF el toggle no aplica). */
    /** Colores distintos que hay en las filas de variantes del formulario: [nombre => nombre]. */
    private static function coloresDeVariantes(mixed $variantes): array
    {
        $colores = collect(is_array($variantes) ? $variantes : [])
            ->map(fn ($fila) => is_array($fila) ? trim((string) ($fila['color'] ?? '')) : '')
            ->filter()
            ->unique()
            ->values();

        return $colores->combine($colores)->all();
    }

    /**
     * Dos filas no pueden repetir talla dentro del mismo color. Si el producto tiene colores,
     * todas las filas deben tener uno (si no, el selector de la web no sabría dónde ponerla).
     */
    private static function reglaDeVarianteUnica(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $filas = collect(is_array($value) ? $value : [])->filter(fn ($fila) => is_array($fila));
            $conColor = $filas->filter(fn ($fila) => filled(trim((string) ($fila['color'] ?? ''))));

            if ($conColor->isNotEmpty() && $conColor->count() !== $filas->count()) {
                $fail('Si el producto tiene colores, todas las variantes deben tener color.');

                return;
            }

            $claves = $filas->map(fn ($fila) => mb_strtolower(trim((string) ($fila['color'] ?? ''))).'|'.($fila['size'] ?? ''));

            if ($claves->count() !== $claves->unique()->count()) {
                $fail('Hay dos variantes con el mismo color y la misma talla.');
            }
        };
    }

    private static function reglaDeUnaPrincipal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $principales = collect(is_array($value) ? $value : [])
                ->filter(fn ($item) => is_array($item)
                    && ($item['is_primary'] ?? false)
                    && in_array(self::tipoDelEstado($item['url'] ?? null), [null, ProductImage::IMAGEN], true))
                ->count();

            if ($principales > 1) {
                $fail('Marca como "Principal" una sola imagen.');
            }
        };
    }

    /**
     * "Poner al principio" en cada fila: la mueve a la primera posición sin pasar por
     * las flechas. Solo cambia el orden del formulario; al guardar, orderColumn
     * renumera sort_order. No toca "Principal".
     */
    private static function accionPonerAlPrincipio(): Action
    {
        return Action::make('ponerAlPrincipio')
            ->label('Poner al principio')
            ->icon('heroicon-m-chevron-double-up')
            ->link()
            ->disabled(fn (array $arguments, Forms\Components\Repeater $component): bool => array_key_first($component->getState() ?? []) === ($arguments['item'] ?? null))
            ->action(function (array $arguments, Forms\Components\Repeater $component): void {
                $items = $component->getState() ?? [];
                $uuid = $arguments['item'] ?? null;

                if (! is_string($uuid) || ! array_key_exists($uuid, $items)) {
                    return;
                }

                $component->state([$uuid => $items[$uuid]] + $items);
                $component->callAfterStateUpdated();
            });
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
                // No ocultables: Filament solo ordena por columnas visibles, y el
                // selector "Ordenar por" las ofrece.
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('variants_sum_stock')
                    ->label('Stock')
                    ->sum('variants', 'stock')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    // Subconsulta propia en vez del alias del sum(): no depende de cómo
                    // arme Filament el select y funciona igual en MySQL y SQLite. Sin
                    // variantes cuenta como 0, igual que lo que se muestra.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        ProductVariant::query()
                            ->selectRaw('COALESCE(SUM(stock), 0)')
                            ->whereColumn('product_variants.product_id', 'products.id'),
                        $direction
                    )),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                // Siempre visible: es el orden por defecto de la tabla y una opción del
                // selector, y Filament no ordena por columnas ocultas.
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                // Mismas columnas que se pueden ordenar desde el encabezado.
                FiltroDeOrden::make([
                    'name' => 'Nombre',
                    'sku' => 'SKU',
                    'category.name' => 'Categoría',
                    'price' => 'Precio',
                    'variants_sum_stock' => 'Stock total',
                    'is_active' => 'Estado',
                    'created_at' => 'Fecha de creación',
                ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Categoría'),
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->label('Marca'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Destacado'),
                Tables\Filters\TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            // Plegado por defecto: se abre con "Filtros y orden". Orden (2) + categoría +
            // marca en la primera fila; el resto en la segunda.
            ->filtersFormColumns(4)
            ->filtersTriggerAction(fn (Tables\Actions\Action $action) => FiltroDeOrden::botonPanel($action))
            // Juntos: el orden y el selector se guardan y vuelven a la par al volver de
            // editar un producto (ver SincronizaOrdenConFiltro).
            ->persistSortInSession()
            ->persistFiltersInSession()
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
