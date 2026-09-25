<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Catálogo de transportistas para el tracking. Las tarifas que ve el cliente se
 * administran en "Opciones de envío" (ShippingOptionResource).
 */
class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Transportistas';

    protected static ?string $modelLabel = 'Transportista';

    protected static ?string $pluralModelLabel = 'Transportistas';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->placeholder('Ej: DHL')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Se usa al cargar el tracking de un envío.'),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('shipments_count')
                    ->label('Envíos')
                    ->counts('shipments')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'edit' => Pages\EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
