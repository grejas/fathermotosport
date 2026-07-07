<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?string $modelLabel = 'Movimiento';

    protected static ?string $pluralModelLabel = 'Inventario';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Producto')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'in' => 'success',
                        'sale' => 'info',
                        'out' => 'danger',
                        'adjustment' => 'warning',
                        'return' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'in' => 'Entrada',
                        'out' => 'Salida',
                        'sale' => 'Venta',
                        'adjustment' => 'Ajuste',
                        'return' => 'Devolución',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('reason')->label('Razón')->limit(40),
                Tables\Columns\TextColumn::make('user.full_name')->label('Registrado por')->placeholder('Sistema'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options([
                    'in' => 'Entrada', 'out' => 'Salida', 'sale' => 'Venta',
                    'adjustment' => 'Ajuste', 'return' => 'Devolución',
                ]),
                Tables\Filters\SelectFilter::make('product')->relationship('product', 'name')->label('Producto')->searchable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();
        return (bool) ($u?->isAdmin() || $u?->isEmpleado());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
        ];
    }
}
