<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Clientes';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    /** Solo clientes (rol cliente), nunca admins/empleados. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['role:id,name,slug'])
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->withCount('orders');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')->label('')->circular()->defaultImageUrl(asset('favicon.ico')),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre')
                    ->getStateUsing(fn (User $r) => $r->full_name)
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                Tables\Columns\TextColumn::make('orders_count')->label('Pedidos')->badge(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total gastado')
                    ->getStateUsing(fn (User $r) => '$' . number_format($r->orders()->where('payment_status', 'paid')->sum('total'), 2)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('Registro')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'active' => 'Activo', 'inactive' => 'Inactivo', 'banned' => 'Bloqueado',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (User $r) => $r->status === 'active' ? 'Desactivar' : 'Activar')
                    ->icon('heroicon-o-power')
                    ->requiresConfirmation()
                    ->visible(fn () => (bool) auth()->user()?->isAdmin()) // solo Admin
                    ->action(fn (User $r) => $r->update([
                        'status' => $r->status === 'active' ? 'inactive' : 'active',
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Datos del cliente')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('full_name')->label('Nombre')->getStateUsing(fn (User $r) => $r->full_name),
                    Infolists\Components\TextEntry::make('email')->label('Email')->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                    Infolists\Components\TextEntry::make('status')->label('Estado')->badge(),
                    Infolists\Components\TextEntry::make('created_at')->label('Registro')->date(),
                ]),
            Infolists\Components\Section::make('Cupón de bienvenida')
                ->schema([
                    Infolists\Components\TextEntry::make('loyalty_discount_used')
                        ->label('Cupón de $5')
                        ->getStateUsing(fn (User $r) => $r->loyalty_discount_used ? 'Usado' : 'Disponible')
                        ->badge()
                        ->color(fn (User $r) => $r->loyalty_discount_used ? 'gray' : 'warning'),
                ]),
            Infolists\Components\Section::make('Resumen de compras')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('orders_count')
                        ->label('Pedidos realizados')
                        ->getStateUsing(fn (User $r) => $r->orders()->count()),
                    Infolists\Components\TextEntry::make('total_historico')
                        ->label('Total gastado (pagado)')
                        ->getStateUsing(fn (User $r) => '$' . number_format($r->orders()->where('payment_status', 'paid')->sum('total'), 2)),
                ]),
        ]);
    }

    public static function canAccess(): bool
    {
        // Empleado y Admin pueden VER clientes (solo lectura); nadie edita su rol.
        return (bool) auth()->user()?->isStaff();
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
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
