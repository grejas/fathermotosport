<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Models\ReviewImage;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static int $defaultPaginationPageOption = 10;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Clientes';

    protected static ?string $navigationLabel = 'Reseñas';

    protected static ?string $modelLabel = 'Reseña';

    protected static ?string $pluralModelLabel = 'Reseñas';

    protected static ?int $navigationSort = 2;

    /** Reseñas sin aprobar más fotos pendientes: lo que espera revisión. */
    public static function contarPendientes(): int
    {
        return static::getModel()::where('is_approved', false)->count()
            + ReviewImage::where('status', ReviewImage::PENDIENTE)->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::contarPendientes();

        return $pending > 0 ? (string) $pending : null;
    }

    /**
     * Aviso tras "Aprobar todo" (fila, masiva o ventana "Ver") o tras aprobar las fotos.
     * Si alguna foto no se pudo mover se dice cuál, y el aviso queda hasta cerrarlo.
     *
     * @param  list<string>  $fallidas
     */
    public static function avisarAprobacion(int $resenas, int $fotos, array $fallidas = []): void
    {
        $aviso = Notification::make()->title($resenas + $fotos > 0
            ? 'Aprobado: '.($resenas === 1 ? '1 reseña' : "{$resenas} reseñas").', '.($fotos === 1 ? '1 foto' : "{$fotos} fotos")
            : 'No había nada pendiente');

        if ($fallidas === []) {
            $aviso->success()->send();

            return;
        }

        $aviso->warning()->persistent()
            ->body('No se pudo mover '.(count($fallidas) === 1 ? 'esta foto' : 'estas fotos').' (archivo faltante o ilegible): '
                .implode('; ', $fallidas).'. Siguen pendientes y sin publicar; elimínalas o revisa el archivo en el servidor.')
            ->send();
    }

    private static function tienePendientes(Review $r): bool
    {
        return isset($r->fotos_pendientes_count)
            ? ! $r->is_approved || $r->fotos_pendientes_count > 0
            : $r->tienePendientes();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'images',
                'images as fotos_pendientes_count' => fn (Builder $q) => $q->where('status', ReviewImage::PENDIENTE),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Producto')->limit(28)->searchable(),
                Tables\Columns\TextColumn::make('user.full_name')->label('Cliente')->getStateUsing(fn (Review $r) => $r->user?->full_name),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Calificación')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->color('warning'),
                Tables\Columns\TextColumn::make('title')->label('Título')->limit(30),
                Tables\Columns\TextColumn::make('images_count')
                    ->label('Fotos')
                    ->badge()
                    ->formatStateUsing(fn (Review $r) => $r->fotos_pendientes_count > 0
                        ? "{$r->images_count} ({$r->fotos_pendientes_count} pend.)"
                        : (string) $r->images_count)
                    ->color(fn (Review $r) => $r->fotos_pendientes_count > 0 ? 'warning' : 'gray'),
                Tables\Columns\IconColumn::make('is_approved')->label('Aprobada')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')->label('Aprobada'),
                Tables\Filters\Filter::make('fotos_pendientes')
                    ->label('Con fotos pendientes')
                    ->query(fn (Builder $query) => $query->whereHas('images', fn (Builder $q) => $q->where('status', ReviewImage::PENDIENTE))),
            ])
            ->actions([
                // Ventana con el comentario completo y las fotos (aprobar o eliminar una por una).
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Reseña')
                    ->modalWidth('4xl')
                    ->modalContent(fn (Review $r) => view('filament.resenas.detalle', [
                        'review' => $r->fresh(['user', 'product', 'images']) ?? $r,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                // La reseña y sus fotos pendientes de una vez.
                Tables\Actions\Action::make('aprobar_todo')
                    ->label('Aprobar todo')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Review $r) => static::tienePendientes($r))
                    ->action(function (Review $r) {
                        $hecho = $r->aprobarTodo();
                        static::avisarAprobacion((int) $hecho['resena'], $hecho['fotos'], $hecho['fallidas']);
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Review $r) => ! $r->is_approved)
                    ->action(fn (Review $r) => $r->update(['is_approved' => true])),
                // Retirar = deja de publicarse (y sus fotos con ella); se puede volver a aprobar.
                Tables\Actions\Action::make('retirar')
                    ->label('Retirar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Review $r) => $r->is_approved)
                    ->action(fn (Review $r) => $r->update(['is_approved' => false])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_all')
                        ->label('Aprobar seleccionadas')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                    // Cada reseña en su propia transacción: una que falle no frena a las demás.
                    Tables\Actions\BulkAction::make('aprobar_todo')
                        ->label('Aprobar todo en las seleccionadas')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar todo en las seleccionadas')
                        ->modalDescription('Se aprueban las reseñas y todas sus fotos pendientes, y se publican en la web.')
                        ->modalSubmitActionLabel('Aprobar todo')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $resenas = 0;
                            $fotos = 0;
                            $fallidas = [];
                            foreach ($records->load(['user', 'product']) as $r) {
                                $hecho = $r->aprobarTodo();
                                $resenas += (int) $hecho['resena'];
                                $fotos += $hecho['fotos'];
                                foreach ($hecho['fallidas'] as $foto) {
                                    $fallidas[] = ($r->user?->full_name ?? 'Cliente').' – '.($r->product?->name ?? 'producto').": {$foto}";
                                }
                            }
                            static::avisarAprobacion($resenas, $fotos, $fallidas);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
