<?php

namespace App\Filament\Support;

use Filament\Forms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Selector visible "Ordenar por / Dirección" para listados del panel.
 *
 * Filament 3 solo muestra su selector de orden nativo en las vistas de tarjetas, no
 * en una tabla normal en desktop; por eso va como filtro. El filtro NO ordena por su
 * cuenta: la página (SincronizaOrdenConFiltro) copia lo elegido al orden de la tabla,
 * así el orden lo aplican las mismas columnas sortable() de siempre, con sus
 * subconsultas, y el clic en el encabezado y el selector nunca se contradicen.
 */
class FiltroDeOrden
{
    public const NOMBRE = 'orden';

    /**
     * Botón que pliega y despliega el panel de filtros. Por defecto Filament pone solo
     * un embudo, que no dice que ahí también está el orden.
     */
    public static function botonPanel(Action $action): Action
    {
        return $action
            ->button()
            ->label('Filtros y orden')
            ->icon('heroicon-m-adjustments-horizontal')
            ->tooltip('Mostrar u ocultar filtros y orden');
    }

    /** @param  array<string, string>  $columnas  nombre de columna de la tabla => etiqueta */
    public static function make(array $columnas): Filter
    {
        return Filter::make(self::NOMBRE)
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('columna')
                        ->label('Ordenar por')
                        ->placeholder('Por defecto (más recientes primero)')
                        ->options($columnas),
                    Forms\Components\Select::make('direccion')
                        ->label('Dirección')
                        ->placeholder('—')
                        ->options([
                            'asc' => 'Ascendente',
                            'desc' => 'Descendente',
                        ]),
                ]),
            ])
            ->columnSpan(2)
            // El orden lo aplica la tabla; acá no se toca la consulta.
            ->query(fn (Builder $query): Builder => $query)
            ->indicateUsing(function (array $data) use ($columnas): array {
                if (blank($data['columna'] ?? null) || ! isset($columnas[$data['columna']])) {
                    return [];
                }

                $direccion = ($data['direccion'] ?? 'asc') === 'desc' ? 'descendente' : 'ascendente';

                return [Indicator::make("Orden: {$columnas[$data['columna']]} ({$direccion})")];
            });
    }
}
