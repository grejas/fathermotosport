<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Support\FiltroDeOrden;
use App\Models\Order;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
                    ->searchable(['first_name', 'last_name'])
                    // full_name no es columna: se ordena como se lee, nombre y después apellido.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('first_name', $direction)
                        ->orderBy('last_name', $direction)),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->sortable()->copyable(),
                // Solo con el correo verificado recibe promociones.
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verificado')
                    ->getStateUsing(fn (User $r) => $r->email_verified_at !== null)
                    ->boolean()
                    ->tooltip(fn (User $r) => $r->email_verified_at ? self::origenVerificacion($r) : 'Correo sin verificar: no recibe promociones'),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                Tables\Columns\TextColumn::make('country')->label('País')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->badge()
                    // Subconsulta propia, no el alias del withCount: funciona igual en
                    // MySQL y SQLite y cuenta lo mismo que se muestra.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Order::query()
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('orders.user_id', 'users.id'),
                        $direction
                    )),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total gastado')
                    ->getStateUsing(fn (User $r) => '$'.number_format($r->orders()->where('payment_status', 'paid')->sum('total'), 2)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('Registro')->date()->sortable(),
            ])
            ->filters([
                // Mismas columnas que se pueden ordenar desde el encabezado.
                FiltroDeOrden::make([
                    'full_name' => 'Nombre',
                    'email' => 'Email',
                    'country' => 'País',
                    'created_at' => 'Fecha de registro',
                    'orders_count' => 'Cantidad de pedidos',
                ]),
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'active' => 'Activo', 'inactive' => 'Inactivo', 'banned' => 'Bloqueado',
                ]),
                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Correo verificado')
                    ->nullable(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(fn (Tables\Actions\Action $action) => FiltroDeOrden::botonPanel($action))
            ->persistSortInSession()
            ->persistFiltersInSession()
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
                Tables\Actions\Action::make('marcar_verificado')
                    ->label('Marcar correo como verificado')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar el correo como verificado')
                    ->modalDescription(fn (User $r) => "Hazlo solo si confirmaste por otro medio que {$r->email} existe y es de este cliente. Desde ahora podrá recibir promociones. Queda registrado quién lo marcó y cuándo.")
                    ->modalSubmitActionLabel('Sí, está verificado')
                    ->visible(fn (User $r) => $r->email_verified_at === null && (bool) auth()->user()?->isAdmin()) // solo Admin
                    ->action(function (User $r) {
                        $r->marcarCorreoVerificado('admin', auth()->user());
                        Log::info('Correo de cliente marcado como verificado a mano.', ['user_id' => $r->id, 'admin_id' => auth()->id()]);
                        Notification::make()->title('Correo marcado como verificado')->success()->send();
                    }),
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
            Infolists\Components\Section::make('Correo y promociones')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('verificacion')
                        ->label('Correo')
                        ->getStateUsing(fn (User $r) => $r->email_verified_at ? 'Verificado: '.self::origenVerificacion($r) : 'Sin verificar')
                        ->badge()
                        ->color(fn (User $r) => $r->email_verified_at ? 'success' : 'warning'),
                    Infolists\Components\TextEntry::make('promociones')
                        ->label('Promociones')
                        ->getStateUsing(fn (User $r) => $r->motivoSinPromociones() === null ? 'Las recibe' : 'No las recibe ('.$r->motivoSinPromociones().')'),
                    Infolists\Components\TextEntry::make('locale')->label('Idioma')->placeholder('—'),
                    Infolists\Components\TextEntry::make('country')->label('País')->placeholder('—'),
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
                        ->getStateUsing(fn (User $r) => '$'.number_format($r->orders()->where('payment_status', 'paid')->sum('total'), 2)),
                ]),
        ]);
    }

    /** "por código, 12/08/2026" o "a mano por Ana Pérez, 03/11/2026". */
    private static function origenVerificacion(User $r): string
    {
        $fecha = $r->email_verified_at?->timezone(config('marketing.timezone'))->format('d/m/Y');
        $via = match ($r->email_verified_via) {
            'code' => 'por código',
            'google' => 'con Google',
            'paypal' => 'por PayPal',
            'password_reset' => 'con el enlace de contraseña',
            'admin' => 'a mano por '.(User::withTrashed()->find($r->email_verified_by)?->full_name ?: 'un administrador'),
            default => 'antes de registrar el origen',
        };

        return "{$via}, {$fecha}";
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
