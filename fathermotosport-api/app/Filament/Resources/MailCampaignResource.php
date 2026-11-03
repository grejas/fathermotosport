<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailCampaignResource\Pages;
use App\Filament\Resources\MailCampaignResource\RelationManagers\DestinatariosRelationManager;
use App\Models\MailCampaign;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Historial de los avisos de descuento por correo (fase 3). Misma presentación que las
 * campañas manuales (CampaignResource): lista con nombre, tipo, estado, fecha de envío y
 * progreso ("18 de 20 enviados", con los fallidos en rojo si hay); detalle con enviados,
 * pendientes y fallidos, el resto solo si es mayor que 0, y los destinatarios con el
 * correo completo. Solo lectura.
 */
class MailCampaignResource extends Resource
{
    protected static ?string $model = MailCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Clientes';

    protected static ?string $navigationLabel = 'Avisos por correo';

    protected static ?string $modelLabel = 'Aviso por correo';

    protected static ?string $pluralModelLabel = 'Avisos por correo';

    protected static ?string $slug = 'avisos-por-correo';

    public const TIPOS = [
        MailCampaign::TIPO_MANUAL => 'Campaña',
        MailCampaign::TIPO_DESCUENTO => 'Aviso de descuento',
    ];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', MailCampaign::TIPO_DESCUENTO);
    }

    public static function colorDeEstado(string $estado): string
    {
        return match ($estado) {
            MailCampaign::COMPLETADA => 'success',
            MailCampaign::CANCELADA => 'danger',
            MailCampaign::BORRADOR => 'gray',
            MailCampaign::PROGRAMADA => 'info',
            default => 'warning',
        };
    }

    /** Las cinco columnas de la lista, iguales en avisos y campañas. */
    public static function columnasDeLista(): array
    {
        $zona = config('marketing.timezone', 'America/La_Paz');

        return [
            Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Tipo')
                ->formatStateUsing(fn (string $state) => self::TIPOS[$state] ?? $state),
            Tables\Columns\TextColumn::make('status')->label('Estado')->badge()
                ->formatStateUsing(fn (string $state) => MailCampaign::ESTADOS[$state] ?? $state)
                ->color(fn (string $state) => self::colorDeEstado($state)),
            Tables\Columns\TextColumn::make('envio')->label('Envío')
                ->state(fn (MailCampaign $r) => $r->fechaDeEnvio()?->timezone($zona)->format('d/m/Y H:i'))
                ->description(fn (MailCampaign $r) => $r->status === MailCampaign::PROGRAMADA ? 'programada' : null)
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('progreso')->label('Progreso')
                ->state(fn (MailCampaign $r) => self::progresoConFallidos($r))
                ->placeholder('—'),
        ];
    }

    /** "18 de 20 enviados" y, solo si hay fallidos, un distintivo rojo con el número. */
    public static function progresoConFallidos(MailCampaign $r): ?HtmlString
    {
        $progreso = $r->progreso();
        if ($progreso === null) {
            return null;
        }

        $fallidos = $r->failed_count > 0
            ? ' <span class="fallidos" style="display:inline-block; margin-left:6px; padding:1px 8px; border-radius:6px; background:#FEE2E2; color:#991B1B; font-size:12px; font-weight:600;">'
                .$r->failed_count.' '.($r->failed_count === 1 ? 'fallido' : 'fallidos').'</span>'
            : '';

        return new HtmlString(e($progreso).$fallidos);
    }

    /** Detalle: una línea con nombre, asunto, estado y autor; enviados, pendientes y fallidos. */
    public static function esquemaDeDetalle(): array
    {
        $zona = config('marketing.timezone', 'America/La_Paz');

        return [
            Infolists\Components\Section::make()->schema([
                Infolists\Components\Grid::make(4)->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nombre'),
                    Infolists\Components\TextEntry::make('asunto')->label('Asunto')->state(fn (MailCampaign $r) => $r->asunto())->placeholder('—'),
                    Infolists\Components\TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn (string $state) => MailCampaign::ESTADOS[$state] ?? $state)
                        ->color(fn (string $state) => self::colorDeEstado($state))
                        ->helperText(fn (MailCampaign $r) => $r->status === MailCampaign::PROGRAMADA && $r->scheduled_at
                            ? 'Sale el '.$r->scheduled_at->timezone($zona)->format('d/m/Y H:i')
                            : ($r->status === MailCampaign::CANCELADA ? $r->cancel_reason : null)),
                    Infolists\Components\TextEntry::make('creador')->label('Creada por')->state(fn (MailCampaign $r) => $r->creador()),
                ]),
                Infolists\Components\Grid::make(3)->schema([
                    Infolists\Components\TextEntry::make('sent_count')->label('Enviados')->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold'),
                    Infolists\Components\TextEntry::make('pending_count')->label('Pendientes')->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold'),
                    Infolists\Components\TextEntry::make('failed_count')->label('Fallidos')->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold')
                        ->color(fn (MailCampaign $r) => $r->failed_count > 0 ? 'danger' : null),
                ]),
                // Omitidos y bajas: solo si alguno es mayor que 0, discreto.
                Infolists\Components\TextEntry::make('detalles')->hiddenLabel()->color('gray')->size(Infolists\Components\TextEntry\TextEntrySize::Small)
                    ->state(fn (MailCampaign $r) => $r->detallesSecundarios())
                    ->visible(fn (MailCampaign $r) => $r->detallesSecundarios() !== null),
            ]),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(self::esquemaDeDetalle());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::columnasDeLista())
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    MailCampaign::ENVIANDO => 'Enviando',
                    MailCampaign::COMPLETADA => 'Enviada',
                    MailCampaign::CANCELADA => 'Cancelada',
                ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('Detalle')])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [DestinatariosRelationManager::class];
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
            'index' => Pages\ListMailCampaigns::route('/'),
            'view' => Pages\ViewMailCampaign::route('/{record}'),
        ];
    }
}
