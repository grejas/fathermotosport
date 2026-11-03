<?php

namespace App\Filament\Resources\MailCampaignResource\RelationManagers;

use App\Models\MailCampaignRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Destinatarios de un aviso o campaña, para el personal del panel: el correo COMPLETO
 * (sin ocultar), estado, fecha de envío y, solo en omitidos y fallidos, el motivo en
 * lenguaje claro (y los intentos en los fallidos). Se busca por correo y se filtra por
 * estado. Solo lectura. Los correos no salen de acá: ninguna API pública
 * ni log los muestra.
 */
class DestinatariosRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Destinatarios';

    /** Se carga con la página (no en una segunda petición): es la parte principal del detalle. */
    protected static bool $isLazy = false;

    protected static ?string $modelLabel = 'destinatario';

    protected static ?string $pluralModelLabel = 'destinatarios';

    public const ESTADOS = [
        MailCampaignRecipient::PENDIENTE => 'Pendiente',
        MailCampaignRecipient::ENVIANDO => 'Enviando',
        MailCampaignRecipient::ENVIADO => 'Enviado',
        MailCampaignRecipient::FALLIDO => 'Fallido',
        MailCampaignRecipient::OMITIDO => 'Omitido',
        MailCampaignRecipient::CANCELADO => 'Cancelado',
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $zona = config('marketing.timezone', 'America/La_Paz');

        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable()->sortable()->copyable()->wrap(),
                Tables\Columns\TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => self::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        MailCampaignRecipient::ENVIADO => 'success',
                        MailCampaignRecipient::FALLIDO => 'danger',
                        MailCampaignRecipient::OMITIDO, MailCampaignRecipient::CANCELADO => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('sent_at')->label('Enviado')->dateTime('d/m/Y H:i', $zona)->placeholder('—')->sortable(),
                // Solo en omitidos y fallidos, en lenguaje claro; los intentos, solo en fallidos.
                Tables\Columns\TextColumn::make('motivo')->label('Motivo')
                    ->state(fn (MailCampaignRecipient $r) => $r->motivoLegible())
                    ->description(fn (MailCampaignRecipient $r) => $r->status === MailCampaignRecipient::FALLIDO ? "{$r->attempts} intentos" : null)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options(self::ESTADOS),
            ])
            ->defaultSort('id')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
