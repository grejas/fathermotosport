<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Métodos de envío';

    protected static ?string $modelLabel = 'Método de envío';

    protected static ?string $pluralModelLabel = 'Métodos de envío';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\Select::make('country')
                ->label('País')
                ->options(['Bolivia' => 'Bolivia', 'Brasil' => 'Brasil'])
                ->required(),
            Forms\Components\TextInput::make('price')
                ->label('Precio')
                ->numeric()
                ->prefix('$')
                ->default(0.00)
                ->hint('0 = Envío gratuito'),
            Forms\Components\TextInput::make('delivery_days_min')->label('Días mín.')->numeric()->default(2),
            Forms\Components\TextInput::make('delivery_days_max')->label('Días máx.')->numeric()->default(5),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('country')->label('País')->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$' . number_format($state, 2) : 'Gratis')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('delivery_days_min')
                    ->label('Entrega')
                    ->formatStateUsing(fn ($state, ShippingMethod $r) => "{$r->delivery_days_min}-{$r->delivery_days_max} días"),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
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
        $u = auth()->user();
        return (bool) ($u?->isAdmin() || $u?->isEmpleado());
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'edit' => Pages\EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
