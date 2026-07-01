<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\EmailService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Response;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $isAdmin = fn () => (bool) auth()->user()?->isAdmin();

        return $form->schema([
            Forms\Components\Section::make('Información del pedido')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('order_number')->label('N° de pedido')->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'pending' => 'Pendiente',
                            'processing' => 'En preparación',
                            'shipped' => 'Enviado',
                            'delivered' => 'Entregado',
                            'cancelled' => 'Cancelado',
                        ])
                        ->required(),
                    Forms\Components\Select::make('payment_status')
                        ->label('Estado del pago')
                        ->options([
                            'pending' => 'Pendiente',
                            'paid' => 'Pagado',
                            'failed' => 'Fallido',
                            'refunded' => 'Reembolsado',
                        ])
                        ->disabled(fn () => ! auth()->user()?->isAdmin()),
                    Forms\Components\Select::make('shipping_status')
                        ->label('Estado del envío')
                        ->options([
                            'pending' => 'Pendiente',
                            'preparing' => 'Preparando',
                            'in_transit' => 'En tránsito',
                            'delivered' => 'Entregado',
                        ]),
                    Forms\Components\TextInput::make('payment_method')->label('Método de pago')->disabled(),
                    Forms\Components\TextInput::make('country')->label('País')->disabled(),
                    Forms\Components\Textarea::make('notes')->label('Notas')->columnSpanFull()->rows(2),
                ]),

            Forms\Components\Section::make('Cliente')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('cliente')
                        ->label('Cliente')
                        ->content(fn (?Order $record) => $record
                            ? ($record->user?->full_name ?? $record->guest_email ?? 'Invitado')
                            : '—'),
                    Forms\Components\Placeholder::make('contacto')
                        ->label('Contacto')
                        ->content(fn (?Order $record) => $record
                            ? ($record->user?->email ?? $record->guest_email ?? '—')
                            : '—'),
                    Forms\Components\Placeholder::make('direccion')
                        ->label('Dirección de entrega')
                        ->columnSpanFull()
                        ->content(function (?Order $record) {
                            $a = $record?->address;
                            if (! $a) {
                                return '—';
                            }
                            return "{$a->full_name} · {$a->phone}\n{$a->address_line}, {$a->city}, {$a->country}"
                                . ($a->reference ? " ({$a->reference})" : '');
                        }),
                ]),

            Forms\Components\Section::make('Items del pedido')
                ->schema([
                    Forms\Components\Placeholder::make('items')
                        ->hiddenLabel()
                        ->content(function (?Order $record) {
                            if (! $record) {
                                return '—';
                            }
                            $lines = $record->items->map(function ($item) {
                                $name = optional(optional($item->variant)->product)->name ?? 'Producto';
                                $variant = trim(($item->size ?? '') . ' ' . ($item->color ?? ''));
                                return "• {$name} " . ($variant ? "({$variant}) " : '')
                                    . "x{$item->quantity} — $" . number_format($item->subtotal, 2);
                            })->implode("\n");
                            return $lines ?: 'Sin items';
                        }),
                ]),

            Forms\Components\Section::make('Totales')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('subtotal_p')->label('Subtotal')
                        ->content(fn (?Order $r) => '$' . number_format($r?->subtotal ?? 0, 2)),
                    Forms\Components\Placeholder::make('discount_p')->label('Descuento')
                        ->content(fn (?Order $r) => '$' . number_format($r?->discount ?? 0, 2)),
                    Forms\Components\Placeholder::make('shipping_p')->label('Envío')
                        ->content('$0.00 · Gratis'),
                    Forms\Components\Placeholder::make('total_p')->label('Total')
                        ->content(fn (?Order $r) => '$' . number_format($r?->total ?? 0, 2)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N° pedido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->getStateUsing(fn (Order $r) => $r->user?->full_name ?? $r->guest_email ?? 'Invitado')
                    ->searchable(query: fn ($query, $search) => $query->where('guest_email', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pago')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('shipping_status')
                    ->label('Envío')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'delivered' => 'success',
                        'in_transit' => 'info',
                        'preparing' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_method')->label('Método')->badge(),
                Tables\Columns\TextColumn::make('country')->label('País')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'pending' => 'Pendiente', 'processing' => 'En preparación',
                    'shipped' => 'Enviado', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado',
                ]),
                Tables\Filters\SelectFilter::make('payment_status')->label('Pago')->options([
                    'pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado',
                ]),
                Tables\Filters\SelectFilter::make('country')->label('País')->options([
                    'Bolivia' => 'Bolivia', 'Brasil' => 'Brasil',
                ]),
                Tables\Filters\SelectFilter::make('payment_method')->label('Método')->options([
                    'paypal' => 'PayPal', 'stripe' => 'Stripe', 'mercadopago' => 'MercadoPago',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('tracking')
                    ->label('Tracking')
                    ->icon('heroicon-o-truck')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')->label('N° de seguimiento')->required(),
                        Forms\Components\TextInput::make('carrier')->label('Transportista'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->shipment()->updateOrCreate(
                            ['order_id' => $record->id],
                            [
                                'tracking_number' => $data['tracking_number'],
                                'carrier' => $data['carrier'] ?? null,
                                'status' => 'in_transit',
                                'shipped_at' => now(),
                            ]
                        );
                        $record->update(['status' => 'shipped', 'shipping_status' => 'in_transit']);
                        app(EmailService::class)->sendShippingUpdate($record, $data['tracking_number']);
                    })
                    ->successNotificationTitle('Tracking registrado y cliente notificado'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('procesando')
                        ->label('Marcar en preparación')
                        ->icon('heroicon-o-clock')
                        ->action(fn ($records) => $records->each->update(['status' => 'processing'])),
                    Tables\Actions\BulkAction::make('enviado')
                        ->label('Marcar como enviado')
                        ->icon('heroicon-o-truck')
                        ->action(fn ($records) => $records->each->update(['status' => 'shipped', 'shipping_status' => 'in_transit'])),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Exportar a CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn ($records) => static::exportCsv($records)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Genera y descarga un CSV con los pedidos seleccionados. */
    protected static function exportCsv($records)
    {
        $headers = ['N° pedido', 'Cliente', 'Email', 'Total', 'Pago', 'Envío', 'País', 'Fecha'];

        $callback = function () use ($records, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($records as $r) {
                fputcsv($out, [
                    $r->order_number,
                    $r->user?->full_name ?? 'Invitado',
                    $r->user?->email ?? $r->guest_email,
                    $r->total,
                    $r->payment_status,
                    $r->shipping_status,
                    $r->country,
                    optional($r->created_at)->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        };

        return Response::streamDownload($callback, 'pedidos-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public static function canCreate(): bool
    {
        return false; // Los pedidos se crean desde la tienda, no desde el panel.
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin(); // El empleado no elimina pedidos.
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
