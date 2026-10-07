<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UpsellRuleResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Models\UpsellRule;
use App\Services\UpsellService;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class UpsellRuleResource extends Resource
{
    protected static ?string $model = UpsellRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Venta cruzada';

    protected static ?string $modelLabel = 'Regla de venta cruzada';

    protected static ?string $pluralModelLabel = 'Reglas de venta cruzada';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('La regla')
                ->description('Quien compre el disparador verá la oferta con descuento en la pantalla de "pedido confirmado". Una categoría incluye sus subcategorías; si se ofrece una categoría, al cliente se le muestra uno de sus productos.')
                ->columns(2)
                ->schema([
                    Forms\Components\Group::make([
                        self::selectorDeTipo('trigger'),
                        Forms\Components\Select::make('trigger_product_id')
                            ->label('Si compra este producto')
                            ->options(fn () => self::opcionesDeProducto())
                            ->optionsLimit(self::sinLimiteDeOpciones())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // El disparador no puede estar también entre los ofrecidos.
                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set(
                                'offer_product_ids',
                                array_values(array_diff($get('offer_product_ids') ?? [], [$state]))
                            ))
                            ->visible(fn (Get $get) => $get('trigger_tipo') !== 'categoria'),
                        Forms\Components\Select::make('trigger_category_id')
                            ->label('Si compra algo de esta categoría')
                            ->options(fn () => self::opcionesDeCategoria())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->visible(fn (Get $get) => $get('trigger_tipo') === 'categoria'),
                    ]),
                    Forms\Components\Group::make([
                        self::selectorDeTipo('offer'),
                        Forms\Components\Select::make('offer_product_ids')
                            ->label('Ofrecerle estos productos')
                            ->helperText('Una regla por producto. Los que ya tengan regla con este disparador se omiten.')
                            ->multiple()
                            ->options(fn (Get $get) => Arr::except(self::opcionesDeProducto(), array_filter([$get('trigger_product_id')])))
                            // Sin maxItems y sin el tope de 50 de Filament: todos los activos.
                            ->optionsLimit(self::sinLimiteDeOpciones())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // Al editar: la regla tiene un solo producto, que arranca elegido.
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?UpsellRule $record) {
                                if (blank($component->getState()) && $record?->offer_product_id) {
                                    $component->state([$record->offer_product_id]);
                                }
                            })
                            ->visible(fn (Get $get) => $get('offer_tipo') !== 'categoria'),
                        Forms\Components\Select::make('offer_category_id')
                            ->label('Ofrecerle algo de esta categoría')
                            ->options(fn () => self::opcionesDeCategoria())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->visible(fn (Get $get) => $get('offer_tipo') === 'categoria'),
                    ]),
                ]),

            Forms\Components\Section::make('Condiciones')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('discount_percent')
                        ->label('Descuento')
                        ->helperText('Para todos, salvo los que tengan uno propio abajo.')
                        ->numeric()
                        ->integer()
                        ->suffix('%')
                        ->minValue(1)
                        ->maxValue(90)
                        ->required()
                        ->live(debounce: 400),
                    Forms\Components\TextInput::make('priority')
                        ->label('Prioridad')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->minValue(0)
                        ->live(debounce: 400)
                        ->helperText('Gana la más alta; a igual prioridad, el mejor descuento. Solo entran '.UpsellService::MAX_OFERTAS.' ofertas por pantalla.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->live()
                        ->inline(false),

                    Forms\Components\Repeater::make('descuentos')
                        ->label('Descuento distinto por producto ofrecido (opcional)')
                        ->columnSpanFull()
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar descuento propio')
                        ->reorderable(false)
                        ->live()
                        ->visible(fn (Get $get) => $get('offer_tipo') !== 'categoria')
                        ->schema([
                            Forms\Components\Select::make('offer_product_id')
                                ->label('Producto ofrecido')
                                // Solo los elegidos arriba.
                                ->options(fn (Get $get) => Arr::only(self::opcionesDeProducto(), $get('../../offer_product_ids') ?? []))
                                ->searchable()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->required(),
                            Forms\Components\TextInput::make('discount_percent')
                                ->label('Descuento')
                                ->numeric()
                                ->integer()
                                ->suffix('%')
                                ->minValue(1)
                                ->maxValue(90)
                                ->required(),
                        ]),

                    // Para que el admin no tenga que calcular de cabeza ni equivocarse.
                    Forms\Components\Placeholder::make('vista_previa')
                        ->label('Lo que verá el cliente')
                        ->columnSpanFull()
                        ->content(fn (Get $get): HtmlString => self::vistaPrevia($get)),

                    // Repetidas y tope de pantalla, antes de guardar y no como error al final.
                    Forms\Components\Placeholder::make('avisos')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn (Get $get, ?UpsellRule $record): HtmlString => self::avisos($get, $record))
                        ->visible(fn (Get $get, ?UpsellRule $record): bool => self::avisos($get, $record)->toHtml() !== ''),
                ]),
        ]);
    }

    /**
     * Los datos del formulario en la forma que espera UpsellService::guardarReglas.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function datosParaGuardar(array $data): array
    {
        $porCategoria = fn (string $lado) => ($data["{$lado}_tipo"] ?? null) === 'categoria';

        return [
            'trigger_product_id' => $porCategoria('trigger') ? null : ($data['trigger_product_id'] ?? null),
            'trigger_category_id' => $porCategoria('trigger') ? ($data['trigger_category_id'] ?? null) : null,
            'offer_product_ids' => $porCategoria('offer') ? [] : array_values($data['offer_product_ids'] ?? []),
            'offer_category_id' => $porCategoria('offer') ? ($data['offer_category_id'] ?? null) : null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'descuentos' => $porCategoria('offer') ? [] : collect($data['descuentos'] ?? [])
                ->filter(fn (array $d) => filled($d['offer_product_id'] ?? null))
                ->mapWithKeys(fn (array $d) => [$d['offer_product_id'] => (int) $d['discount_percent']])
                ->all(),
            'priority' => $data['priority'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** Estado actual del formulario, leído campo por campo (los ocultos incluidos). */
    private static function datosDe(Get $get): array
    {
        return self::datosParaGuardar(collect([
            'trigger_tipo', 'trigger_product_id', 'trigger_category_id',
            'offer_tipo', 'offer_product_ids', 'offer_category_id',
            'discount_percent', 'descuentos', 'priority', 'is_active',
        ])->mapWithKeys(fn (string $campo) => [$campo => $get($campo)])->all());
    }

    private static function vistaPrevia(Get $get): HtmlString
    {
        $datos = self::datosDe($get);
        $porcentaje = (int) $datos['discount_percent'];

        if ($get('offer_tipo') === 'categoria') {
            $categoria = Category::find($datos['offer_category_id']);

            return self::lineas([$categoria && $porcentaje >= 1
                ? sprintf('Un producto de %s (o sus subcategorías) con −%d%% sobre su precio vigente.', $categoria->name, $porcentaje)
                : 'Elegí la categoría ofrecida y el descuento.']);
        }

        $productos = Product::whereIn('id', $datos['offer_product_ids'])->orderBy('name')->get();

        if ($productos->isEmpty() || $porcentaje < 1) {
            return self::lineas(['Elegí los productos ofrecidos y el descuento.']);
        }

        return self::lineas($productos->map(function (Product $product) use ($datos, $porcentaje) {
            $propio = (int) ($datos['descuentos'][$product->id] ?? $porcentaje);
            $base = (float) ($product->sale_price ?? $product->price);
            $final = round($base * (1 - $propio / 100), 2);

            return sprintf(
                '%s · −%d%% · $%s → $%s  (ahorra $%s)',
                $product->name,
                $propio,
                number_format($base, 2),
                number_format($final, 2),
                number_format($base - $final, 2)
            );
        })->all());
    }

    private static function avisos(Get $get, ?UpsellRule $record): HtmlString
    {
        $datos = self::datosDe($get);
        $upsell = app(UpsellService::class);
        ['nuevas' => $nuevas, 'existentes' => $existentes] = $upsell->planDeReglas($datos, $record?->getKey());
        $html = '';

        if ($existentes->isNotEmpty()) {
            $nombres = $get('offer_tipo') === 'categoria'
                ? Category::whereIn('id', $existentes)->pluck('name')->map(fn ($n) => 'la categoría '.$n)
                : Product::whereIn('id', $existentes)->orderBy('name')->pluck('name');

            $html .= self::parrafo($nuevas->isEmpty()
                ? 'Ya existe una regla con este disparador para '.$nombres->implode(', ').'. Buscala en el listado para editarla.'
                : sprintf('Ya tienen regla con este disparador y se omitirán: %s.', $nombres->implode(', ')), 'text-warning-600 dark:text-warning-400');
        }

        $total = $upsell->ofertasActivasTrasGuardar($datos, $record?->getKey());

        if ($total > UpsellService::MAX_OFERTAS) {
            $html .= self::parrafo(sprintf(
                'Nota: este disparador quedará con %d ofertas activas. Al cliente se le muestran solo %d por pedido: las de mayor prioridad y, a igual prioridad, mejor descuento.',
                $total,
                UpsellService::MAX_OFERTAS
            ), 'text-warning-600 dark:text-warning-400');
        }

        return new HtmlString($html);
    }

    /** @param  array<int, string>  $lineas */
    private static function lineas(array $lineas): HtmlString
    {
        return new HtmlString(collect($lineas)->map(fn (string $l) => e($l))->implode('<br>'));
    }

    private static function parrafo(string $texto, string $clase = ''): string
    {
        return '<p class="text-sm '.$clase.'">'.e($texto).'</p>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['triggerProduct', 'triggerCategory', 'offerProduct', 'offerCategory']))
            ->columns([
                Tables\Columns\TextColumn::make('disparador')
                    ->label('Si compra')
                    ->getStateUsing(fn (UpsellRule $r) => $r->descripcionDisparador())
                    ->searchable(query: fn (Builder $query, string $search) => self::buscarPorNombre($query, $search, 'trigger'))
                    ->wrap(),
                Tables\Columns\TextColumn::make('oferta')
                    ->label('Se le ofrece')
                    ->getStateUsing(fn (UpsellRule $r) => $r->descripcionOferta())
                    ->searchable(query: fn (Builder $query, string $search) => self::buscarPorNombre($query, $search, 'offer'))
                    ->wrap(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Descuento')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (int $state) => "−{$state}%"),
                Tables\Columns\TextColumn::make('precio')
                    ->label('Queda en')
                    // Una categoría no tiene un precio único: depende del producto.
                    ->getStateUsing(fn (UpsellRule $r) => $r->ofreceCategoria() ? 'Según el producto' : '$'.number_format($r->precioConDescuento(), 2)),
                Tables\Columns\TextColumn::make('priority')->label('Prioridad')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('priority', 'desc')
            ->emptyStateHeading('Todavía no hay reglas de venta cruzada');
    }

    /** @return array<string, string> */
    public static function opcionesDeProducto(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * "Cascos → Integrales" en vez de solo "Integrales", para distinguir subcategorías
     * que se llaman igual en ramas distintas.
     *
     * @return array<int, string>
     */
    public static function opcionesDeCategoria(): array
    {
        return Category::with('parent')
            ->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->parent ? $c->parent->name.' → '.$c->name : $c->name])
            ->sort()
            ->all();
    }

    /**
     * ¿El disparador (o la oferta) es un producto o una categoría? No es una columna:
     * se deduce de cuál de las dos está cargada. Se envía con el formulario porque
     * datosParaGuardar() decide con él qué lado guardar y cuál dejar en null. Al
     * cambiarlo se vacía ese lado.
     */
    private static function selectorDeTipo(string $lado): Forms\Components\Radio
    {
        return Forms\Components\Radio::make("{$lado}_tipo")
            ->hiddenLabel()
            ->options(['producto' => 'Un producto', 'categoria' => 'Una categoría'])
            ->default('producto')
            ->inline()
            ->live()
            ->afterStateHydrated(fn (Forms\Components\Radio $component, ?UpsellRule $record) => $component->state(
                $record?->getAttribute("{$lado}_category_id") !== null ? 'categoria' : 'producto'
            ))
            ->afterStateUpdated(function (Set $set) use ($lado) {
                $set("{$lado}_category_id", null);

                if ($lado === 'trigger') {
                    $set('trigger_product_id', null);
                } else {
                    $set('offer_product_ids', []);
                    $set('descuentos', []);
                }
            });
    }

    private static function buscarPorNombre(Builder $query, string $search, string $lado): Builder
    {
        $porNombre = fn (Builder $q) => $q->where('name', 'like', "%{$search}%");

        return $query->where(fn (Builder $q) => $q
            ->whereHas("{$lado}Product", $porNombre)
            ->orWhereHas("{$lado}Category", $porNombre));
    }

    /**
     * Para ->optionsLimit(): que el selector muestre todas sus opciones. Filament deja
     * 50 por defecto, y lo que pasa de ahí no aparece en la lista y la búsqueda nunca
     * devuelve más de 50: con un catálogo grande, parte de los productos no se podía
     * elegir.
     */
    public static function sinLimiteDeOpciones(): Closure
    {
        return fn (Forms\Components\Select $component): int => max(50, count($component->getOptions()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUpsellRules::route('/'),
            'create' => Pages\CreateUpsellRule::route('/create'),
            'edit' => Pages\EditUpsellRule::route('/{record}/edit'),
        ];
    }
}
