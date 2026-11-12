<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PopupLeadResource\Pages;
use App\Models\PopupLead;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Contactos captados por el popup de la web. Solo lectura: se generan solos.
 * Se pueden filtrar y descargar en CSV (Excel) desde la cabecera de la lista.
 */
class PopupLeadResource extends Resource
{
    protected static ?string $model = PopupLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Contactos del popup';

    protected static ?string $modelLabel = 'Contacto';

    protected static ?string $pluralModelLabel = 'Contactos del popup';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('whatsapp')->label('WhatsApp')->searchable()->copyable()->placeholder('—'),
                Tables\Columns\TextColumn::make('country')->label('País')->placeholder('—'),
                Tables\Columns\TextColumn::make('percent')->label('Descuento')->suffix(' %')->sortable(),
                Tables\Columns\TextColumn::make('coupon.code')->label('Cupón')->copyable(),
                Tables\Columns\IconColumn::make('usado')
                    ->label('Usado')
                    ->boolean()
                    ->getStateUsing(fn (PopupLead $r) => (int) ($r->coupon?->used_count ?? 0) > 0),
                Tables\Columns\TextColumn::make('source.utm_source')->label('Origen')->placeholder('directo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('percent')->label('Descuento')
                    ->options([10 => '10 %', 15 => '15 %', 20 => '20 %', 25 => '25 %']),
                Tables\Filters\TernaryFilter::make('whatsapp')->label('Con WhatsApp')
                    ->nullable(),
            ])
            ->actions([])
            ->bulkActions([])
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
            'index' => Pages\ListPopupLeads::route('/'),
        ];
    }
}
