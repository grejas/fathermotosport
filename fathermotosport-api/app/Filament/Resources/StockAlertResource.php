<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAlertResource\Pages;
use App\Models\StockAlert;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * «Avísame cuando esté disponible»: clientes que esperan un producto agotado.
 * Se generan solos desde la ficha del producto. El equipo los avisa (WhatsApp o
 * email) cuando vuelve el stock y marca «Avisado».
 */
class StockAlertResource extends Resource
{
    protected static ?string $model = StockAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Avisos de stock';

    protected static ?string $modelLabel = 'Aviso de stock';

    protected static ?string $pluralModelLabel = 'Avisos de stock';

    public static function getNavigationBadge(): ?string
    {
        $pendientes = StockAlert::whereNull('notified_at')->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Stock actual de la talla pedida (o de todo el producto si no eligió talla). */
    public static function stockActual(StockAlert $r): int
    {
        $variantes = $r->product?->variants ?? collect();

        return (int) ($r->size
            ? $variantes->where('size', $r->size)->sum('stock')
            : $variantes->sum('stock'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('product.variants');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Producto')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('size')->label('Talla')->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('whatsapp')->label('WhatsApp')->searchable()->copyable()->placeholder('—')
                    ->url(fn (StockAlert $r) => $r->whatsapp ? 'https://wa.me/'.preg_replace('/\D/', '', $r->whatsapp) : null, true),
                Tables\Columns\TextColumn::make('locale')->label('Idioma')->placeholder('—'),
                Tables\Columns\TextColumn::make('stock_actual')->label('Stock hoy')
                    ->getStateUsing(fn (StockAlert $r) => self::stockActual($r))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('notified_at')->label('Avisado')->dateTime('d/m/Y H:i')->placeholder('pendiente')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('notified_at')->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Avisados')
                    ->falseLabel('Pendientes')
                    ->nullable()
                    ->default(false),
            ])
            ->actions([
                Tables\Actions\Action::make('avisado')
                    ->label('Marcar avisado')
                    ->icon('heroicon-o-check')
                    ->visible(fn (StockAlert $r) => $r->notified_at === null)
                    ->action(fn (StockAlert $r) => $r->update(['notified_at' => now()])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('avisados')
                    ->label('Marcar avisados')
                    ->icon('heroicon-o-check')
                    ->action(fn (Collection $records) => StockAlert::whereKey($records->modelKeys())->update(['notified_at' => now()]))
                    ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListStockAlerts::route('/'),
        ];
    }
}
