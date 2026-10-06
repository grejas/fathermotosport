<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UpsellRuleResource\Pages;
use App\Models\Product;
use App\Models\UpsellRule;
use App\Services\UpsellService;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                ->description('Quien compre el producto disparador verá el ofrecido con descuento en la pantalla de "pedido confirmado".')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('trigger_product_id')
                        ->label('Si compra este producto')
                        ->options(fn () => self::opcionesDeProducto())
                        ->optionsLimit(self::sinLimiteDeOpciones())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->different('offer_product_id')
                        ->validationMessages([
                            'different' => 'El producto ofrecido tiene que ser distinto del disparador.',
                        ]),
                    Forms\Components\Select::make('offer_product_id')
                        ->label('Ofrecerle este otro')
                        ->options(fn () => self::opcionesDeProducto())
                        ->optionsLimit(self::sinLimiteDeOpciones())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->different('trigger_product_id'),
                ]),

            Forms\Components\Section::make('Condiciones')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('discount_percent')
                        ->label('Descuento')
                        ->numeric()
                        ->suffix('%')
                        ->minValue(1)
                        ->maxValue(90)
                        ->required()
                        ->live(debounce: 400),
                    Forms\Components\TextInput::make('priority')
                        ->label('Prioridad')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Gana la más alta. Solo entran '.UpsellService::MAX_OFERTAS.' ofertas por pantalla.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->inline(false),

                    // Para que el admin no tenga que calcular de cabeza ni equivocarse.
                    Forms\Components\Placeholder::make('vista_previa')
                        ->label('Lo que verá el cliente')
                        ->columnSpanFull()
                        ->content(function (Get $get): string {
                            $product = Product::find($get('offer_product_id'));
                            $porcentaje = (int) $get('discount_percent');

                            if (! $product || $porcentaje < 1) {
                                return 'Elegí el producto ofrecido y el descuento.';
                            }

                            $base = (float) ($product->sale_price ?? $product->price);
                            $final = round($base * (1 - $porcentaje / 100), 2);

                            return sprintf(
                                '%s · $%s → $%s  (ahorra $%s)',
                                $product->name,
                                number_format($base, 2),
                                number_format($final, 2),
                                number_format($base - $final, 2)
                            );
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('triggerProduct.name')
                    ->label('Si compra')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('offerProduct.name')
                    ->label('Se le ofrece')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Descuento')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (int $state) => "−{$state}%"),
                Tables\Columns\TextColumn::make('precio')
                    ->label('Queda en')
                    ->getStateUsing(fn (UpsellRule $r) => '$'.number_format($r->precioConDescuento(), 2)),
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
