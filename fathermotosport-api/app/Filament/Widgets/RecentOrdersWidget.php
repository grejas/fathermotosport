<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Pedidos recientes';

    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = null;

    protected static ?int $defaultPaginationPageOption = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with(['user'])->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('N° pedido'),
                Tables\Columns\TextColumn::make('customer')
                    ->label('Cliente')
                    ->getStateUsing(fn (Order $r) => $r->user?->full_name ?? $r->guest_email ?? 'Invitado'),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('USD'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pago')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('shipping_status')
                    ->label('Envío')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'delivered' => 'success',
                        'in_transit' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('country')->label('País')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r]))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}
