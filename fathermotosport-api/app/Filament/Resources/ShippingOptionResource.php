<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingOptionResource\Pages;
use App\Models\ShippingOption;
use App\Support\Countries;
use App\Support\ShippingCountries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingOptionResource extends Resource
{
    protected static ?string $model = ShippingOption::class;

    protected static int $defaultPaginationPageOption = 25;

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Opciones de envío';

    protected static ?string $modelLabel = 'Opción de envío';

    protected static ?string $pluralModelLabel = 'Opciones de envío';

    protected static ?int $navigationSort = 3;

    /** Métodos sugeridos; el admin puede escribir cualquier otro. */
    private const METODOS_SUGERIDOS = ['Estándar', 'Express DHL', 'Express FedEx', 'Courier'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Destino y método')
                ->description('Podés cargar varias opciones para un mismo país (ej. "Estándar" en $0 y "Express DHL" en $25).')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('country_code')
                        ->label('País')
                        // Solo los 40 destinos a los que se envía (mismos que el checkout).
                        ->options(ShippingCountries::options())
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('method_name')
                        ->label('Método')
                        ->options(array_combine(self::METODOS_SUGERIDOS, self::METODOS_SUGERIDOS))
                        ->searchable()
                        ->required()
                        // Permite nombres propios además de los sugeridos.
                        ->createOptionForm([
                            Forms\Components\TextInput::make('method_name')->label('Nombre del método')->required(),
                        ])
                        ->createOptionUsing(fn (array $data) => $data['method_name'])
                        ->helperText('Es el nombre que ve el cliente en el checkout.'),
                ]),

            Forms\Components\Section::make('Rango de peso')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('min_weight_kg')
                        ->label('Peso desde (kg)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('max_weight_kg')
                        ->label('Peso hasta (kg)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->helperText('Vacío = sin límite.')
                        ->gt('min_weight_kg'),
                    Forms\Components\Placeholder::make('rango')
                        ->label('Rango')
                        ->content(fn (Get $get) => filled($get('max_weight_kg'))
                            ? 'De '.($get('min_weight_kg') ?: 0).' a '.$get('max_weight_kg').' kg'
                            : 'De '.($get('min_weight_kg') ?: 0).' kg en adelante'),
                ]),

            Forms\Components\Section::make('Precio y plazo')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Precio')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('0 = envío gratuito.'),
                    Forms\Components\TextInput::make('currency')
                        ->label('Moneda')
                        ->default('USD')
                        ->maxLength(3)
                        ->required(),
                    Forms\Components\TextInput::make('estimated_days_min')
                        ->label('Días estimados (mín.)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0),
                    Forms\Components\TextInput::make('estimated_days_max')
                        ->label('Días estimados (máx.)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->gte('estimated_days_min'),
                    Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('country_code')
                    ->label('País')
                    ->formatStateUsing(fn (?string $state) => static::nombrePais($state).' ('.$state.')')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method_name')
                    ->label('Método')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_weight_kg')
                    ->label('Rango de peso')
                    ->sortable()
                    ->getStateUsing(fn (ShippingOption $r) => $r->max_weight_kg === null
                        ? static::kg($r->min_weight_kg).' kg o más'
                        : static::kg($r->min_weight_kg).' – '.static::kg($r->max_weight_kg).' kg'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$'.number_format((float) $state, 2) : 'Gratis')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_days_min')
                    ->label('Entrega')
                    ->getStateUsing(function (ShippingOption $r) {
                        if ($r->estimated_days_min === null && $r->estimated_days_max === null) {
                            return '—';
                        }

                        return $r->estimated_days_min !== null && $r->estimated_days_max !== null
                            ? "{$r->estimated_days_min}-{$r->estimated_days_max} días"
                            : ($r->estimated_days_min ?? $r->estimated_days_max).' días';
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean()->sortable(),
            ])
            ->defaultSort('country_code')
            ->groups([
                Tables\Grouping\Group::make('country_code')
                    ->label('País')
                    ->getTitleFromRecordUsing(fn (ShippingOption $r) => static::nombrePais($r->country_code)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country_code')
                    ->label('País')
                    ->options(fn () => ShippingOption::query()
                        ->distinct()
                        ->pluck('country_code', 'country_code')
                        ->map(fn ($code) => static::nombrePais($code))
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('method_name')
                    ->label('Método')
                    ->options(fn () => ShippingOption::query()
                        ->distinct()
                        ->pluck('method_name', 'method_name')
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->label('Duplicar'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Nombre del país: primero la lista de destinos; si una fila vieja tuviera un país
     * fuera de esa lista, se cae a la lista completa y, como último recurso, al código.
     */
    private static function nombrePais(?string $code): string
    {
        return ShippingCountries::name($code) ?? Countries::name($code) ?? (string) $code;
    }

    /** 2.500 → "2.5"; 3.000 → "3" */
    private static function kg(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingOptions::route('/'),
            'create' => Pages\CreateShippingOption::route('/create'),
            'edit' => Pages\EditShippingOption::route('/{record}/edit'),
        ];
    }
}
