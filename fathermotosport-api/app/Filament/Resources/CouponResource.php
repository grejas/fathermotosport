<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Cupones';

    protected static ?string $modelLabel = 'Cupón';

    protected static ?string $pluralModelLabel = 'Cupones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Código')
                ->required()
                ->unique(ignoreRecord: true)
                ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                ->dehydrateStateUsing(fn (string $state) => strtoupper($state)),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(['percentage' => 'Porcentaje', 'fixed' => 'Monto fijo'])
                ->default('fixed')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('value')
                ->label('Valor')
                ->numeric()
                ->required()
                ->suffix(fn (Forms\Get $get) => $get('type') === 'percentage' ? '%' : null)
                ->prefix(fn (Forms\Get $get) => $get('type') === 'fixed' ? '$' : null),
            Forms\Components\TextInput::make('minimum_amount')
                ->label('Monto mínimo')
                ->numeric()
                ->prefix('$')
                ->default(0),
            Forms\Components\TextInput::make('max_uses')
                ->label('Usos máximos')
                ->numeric()
                ->helperText('Vacío = ilimitado'),
            Forms\Components\DatePicker::make('start_date')->label('Inicio'),
            Forms\Components\DatePicker::make('end_date')->label('Fin'),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'percentage' ? 'Porcentaje' : 'Fijo'),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->type === 'percentage' ? "{$state}%" : '$' . number_format($state, 2)),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Usos')
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->max_uses ? "{$state}/{$r->max_uses}" : "{$state}/∞"),
                Tables\Columns\TextColumn::make('end_date')->label('Vence')->date()->placeholder('Sin límite'),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options([
                    'percentage' => 'Porcentaje', 'fixed' => 'Fijo',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
