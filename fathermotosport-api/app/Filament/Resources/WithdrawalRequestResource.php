<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\WithdrawalRequest;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * «Desistir de una compra»: clientes que ejercieron el derecho de desistimiento desde la web.
 * Cada uno ya recibió el acuse por correo (copia a ventas@). El equipo coordina la
 * devolución, reembolsa dentro de 14 días y marca «Gestionado». No se borran: son prueba.
 */
class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Desistimientos';

    protected static ?string $modelLabel = 'Desistimiento';

    protected static ?string $pluralModelLabel = 'Desistimientos';

    public static function getNavigationBadge(): ?string
    {
        $pendientes = WithdrawalRequest::whereNull('handled_at')->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Recibido')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('referencia')->label('Ref.')
                    ->getStateUsing(fn (WithdrawalRequest $r) => $r->referencia()),
                Tables\Columns\TextColumn::make('order_number')->label('Pedido')->searchable()->copyable(),
                Tables\Columns\IconColumn::make('order_id')->label('Coincide')
                    ->getStateUsing(fn (WithdrawalRequest $r) => $r->order_id !== null)
                    ->boolean()
                    ->tooltip(fn (WithdrawalRequest $r) => $r->order_id ? 'Pedido y email coinciden' : 'El pedido no existe o el email no coincide: revisar'),
                Tables\Columns\TextColumn::make('name')->label('Cliente')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('items')->label('Productos')->placeholder('Todo el pedido')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('reason')->label('Motivo')->placeholder('—')->wrap()->limit(80),
                Tables\Columns\IconColumn::make('confirmation_sent_at')->label('Acuse')
                    ->getStateUsing(fn (WithdrawalRequest $r) => $r->confirmation_sent_at !== null)
                    ->boolean(),
                Tables\Columns\TextColumn::make('reembolso_limite')->label('Reembolsar antes de')
                    ->getStateUsing(fn (WithdrawalRequest $r) => $r->created_at?->copy()->addDays(14)->format('d/m/Y'))
                    ->color(fn (WithdrawalRequest $r) => $r->handled_at === null && $r->created_at?->copy()->addDays(10)->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('handled_at')->label('Gestionado')->dateTime('d/m/Y H:i')->placeholder('pendiente')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('handled_at')->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Gestionados')
                    ->falseLabel('Pendientes')
                    ->nullable()
                    ->default(false),
            ])
            ->actions([
                Tables\Actions\Action::make('gestionado')
                    ->label('Marcar gestionado')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalDescription('Confírmalo solo cuando la devolución esté coordinada y el reembolso hecho (o programado dentro de los 14 días).')
                    ->visible(fn (WithdrawalRequest $r) => $r->handled_at === null)
                    ->action(fn (WithdrawalRequest $r) => $r->update(['handled_at' => now()])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canAccess(): bool
    {
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
            'index' => Pages\ListWithdrawalRequests::route('/'),
        ];
    }
}
