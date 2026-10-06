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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Response;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static int $defaultPaginationPageOption = 10;

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
            // Solo aparece cuando algo quedó pendiente de resolver a mano. El dinero ya
            // se cobró en estos casos, así que conviene que sea lo primero que se lea.
            Forms\Components\Section::make('⚠️ Este pedido requiere atención')
                ->description('Se cobró el pago, pero algo no cuadró. Revisa y resuelve antes de enviar.')
                ->visible(fn (?Order $record) => filled($record?->attention_reason))
                ->schema([
                    Forms\Components\Placeholder::make('attention_reason_p')
                        ->label('Motivo')
                        ->content(fn (?Order $record) => $record?->attention_reason),
                ]),

            // Aviso de envío conjunto: el upsell no pagó envío porque va en el mismo
            // paquete. Preparar dos envíos anula el ahorro que se le prometió al cliente.
            Forms\Components\Section::make('📦 Se envía junto con otro pedido')
                ->description('Esta venta cruzada no pagó envío: va en el mismo paquete que el pedido original.')
                ->visible(fn (?Order $record) => $record?->esUpsell() ?? false)
                ->schema([
                    Forms\Components\Placeholder::make('upsell_de')
                        ->label('Pedido original')
                        ->content(fn (?Order $record) => $record?->upsellOf?->order_number ?? '—'),
                ]),

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
                            ? ($record->user?->full_name ?? $record->notification_email ?? $record->guest_email ?? 'Invitado')
                            : '—'),
                    Forms\Components\Placeholder::make('contacto')
                        ->label('Contacto')
                        // El email al que se le avisa (el que escribió en la tienda, si lo
                        // hay) y, si PayPal informó otro, ese también: es el verificado,
                        // el que se usa para vincular pedidos y crear la cuenta.
                        ->content(fn (?Order $record) => $record
                            ? collect([
                                $record->emailDelCliente() ?? '—',
                                ($paypal = static::emailDePaypalDistinto($record)) ? "PayPal (verificado): {$paypal}" : null,
                            ])->filter()->implode(' · ')
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
                        ->content(fn (?Order $r) => $r?->shipping > 0
                            ? '$'.number_format($r->shipping, 2).' · '.($r->shipping_method_name ?? 'Envío')
                            : '$0.00 · Gratis'),
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
                // Venta cruzada: viaja en el MISMO paquete que el pedido original, porque
                // no se le cobró envío. Sin esta marca se despachan dos envíos separados.
                Tables\Columns\TextColumn::make('upsellOf.order_number')
                    ->label('Envía con')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->placeholder('—'),
                // Marca los pedidos que necesitan una decisión humana: cobrados sin stock,
                // o con el país que informó PayPal distinto del envío cobrado.
                Tables\Columns\TextColumn::make('attention_reason')
                    ->label('Atención')
                    ->badge()
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->limit(40)
                    ->tooltip(fn (?string $state) => $state),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->getStateUsing(fn (Order $r) => $r->user?->full_name ?? $r->notification_email ?? $r->guest_email ?? 'Invitado')
                    // Encuentra el pedido por el email escrito en la tienda o por el de PayPal.
                    ->searchable(query: fn ($query, $search) => $query->where(fn ($q) => $q
                        ->where('guest_email', 'like', "%{$search}%")
                        ->orWhere('notification_email', 'like', "%{$search}%"))),
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
                // Los abandonos de PayPal se acumulan como pedidos cancelados y tapan lo
                // que sí hay que atender. No se borran: solo se ocultan de la vista general.
                // El estado por defecto de un filtro ternario es "blank", y ese es el que
                // los esconde (mismo patrón que el TrashedFilter de Filament).
                Tables\Filters\TernaryFilter::make('cancelados')
                    ->label('Pedidos cancelados')
                    ->placeholder('Ocultos')
                    ->trueLabel('Mostrar también los cancelados')
                    ->falseLabel('Solo los cancelados')
                    ->queries(
                        true: fn (Builder $query) => $query,
                        false: fn (Builder $query) => $query->where('status', 'cancelled'),
                        blank: fn (Builder $query) => $query->where('status', '!=', 'cancelled'),
                    ),
                Tables\Filters\TernaryFilter::make('attention_reason')
                    ->label('Requiere atención')
                    ->placeholder('Todos')
                    ->trueLabel('Solo los que requieren atención')
                    ->falseLabel('Solo los que están en orden')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('attention_reason'),
                        false: fn (Builder $query) => $query->whereNull('attention_reason'),
                        blank: fn (Builder $query) => $query,
                    ),
                // Sin 'cancelled' a propósito: combinado con el filtro de arriba en su
                // estado por defecto daría una lista siempre vacía. Los cancelados se
                // ven con "Solo los cancelados".
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'pending' => 'Pendiente', 'processing' => 'En preparación',
                    'shipped' => 'Enviado', 'delivered' => 'Entregado',
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
                // Sin esto la insignia de atención sería permanente: hace falta una forma
                // de decir "ya lo resolví" (reponer stock, corregir el envío, etc.).
                Tables\Actions\Action::make('atencion_resuelta')
                    ->label('Atención resuelta')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => filled($record->attention_reason))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $record) => $record->attention_reason)
                    ->action(fn (Order $record) => $record->update(['attention_reason' => null]))
                    ->successNotificationTitle('Pedido marcado como resuelto'),
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
                        app(EmailService::class)->sendShippingUpdate($record, $data['tracking_number'], $data['carrier'] ?? null);
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
            // Los pedidos que requieren atención van arriba; el resto, por fecha.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('attention_reason IS NULL')
                ->orderBy('created_at', 'desc'));
    }

    /**
     * Email que informó PayPal, solo si es distinto del que el cliente escribió en la
     * tienda (si son el mismo, mostrarlo dos veces es ruido).
     */
    public static function emailDePaypalDistinto(Order $order): ?string
    {
        if ($order->email_verificado_por !== 'paypal' || blank($order->guest_email)) {
            return null;
        }

        return strcasecmp($order->guest_email, (string) $order->emailDelCliente()) === 0 ? null : $order->guest_email;
    }

    /** Genera y descarga un CSV con los pedidos seleccionados. */
    protected static function exportCsv($records)
    {
        $headers = ['N° pedido', 'Cliente', 'Email', 'Email PayPal (verificado)', 'Total', 'Pago', 'Envío', 'País', 'Fecha'];

        $callback = function () use ($records, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($records as $r) {
                fputcsv($out, [
                    $r->order_number,
                    $r->user?->full_name ?? 'Invitado',
                    $r->emailDelCliente(),
                    static::emailDePaypalDistinto($r),
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

    public static function getEloquentQuery(): Builder
    {
        // Eager loading del usuario (solo columnas necesarias) para evitar N+1
        // en la columna "Cliente" del listado.
        return parent::getEloquentQuery()->with(['user:id,first_name,last_name,email']);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
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
