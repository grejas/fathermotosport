<?php

namespace App\Filament\Concerns;

use App\Filament\Support\FiltroDeOrden;

/**
 * Para páginas de listado que usan FiltroDeOrden: el selector "Ordenar por" y el clic
 * en los encabezados escriben en el mismo lugar (el orden de la tabla), así que manda
 * el último que se usó y los dos muestran siempre lo mismo.
 *
 * Restablecer o quitar el indicador del filtro vuelve al orden por defecto de la tabla.
 *
 * Con persistSortInSession() y persistFiltersInSession(), Filament guarda cada cosa por
 * su lado y antes de que este trait termine de sincronizarlas. Por eso, al final, se
 * vuelven a guardar las dos: si no, al volver al listado el selector mostraría un orden
 * y la tabla aplicaría otro.
 */
trait SincronizaOrdenConFiltro
{
    /** Clic en un encabezado: además de ordenar, deja el selector mostrando ese orden. */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        parent::sortTable($column, $direction);

        $this->tableFilters[FiltroDeOrden::NOMBRE] = [
            'columna' => $this->tableSortColumn,
            'direccion' => $this->tableSortColumn ? $this->tableSortDirection : null,
        ];

        $this->guardarFiltrosEnSesion();
    }

    /**
     * Livewire avisa qué parte de los filtros cambió. Solo el selector de orden toca el
     * orden: cambiar otro filtro (categoría, estado…) respeta el orden vigente.
     */
    public function updatedTableFilters(mixed $value = null, ?string $key = null): void
    {
        parent::updatedTableFilters();

        if ($key === null || $key === FiltroDeOrden::NOMBRE || str_starts_with($key, FiltroDeOrden::NOMBRE.'.')) {
            $this->aplicarOrdenDelSelector();
        }
    }

    public function resetTableFiltersForm(): void
    {
        parent::resetTableFiltersForm();

        $this->aplicarOrdenDelSelector();
    }

    public function removeTableFilter(string $filterName, ?string $field = null, bool $isRemovingAllFilters = false): void
    {
        parent::removeTableFilter($filterName, $field, $isRemovingAllFilters);

        if ($filterName === FiltroDeOrden::NOMBRE) {
            $this->aplicarOrdenDelSelector();
        }
    }

    /** Copia lo elegido en el selector al orden de la tabla. Vacío = orden por defecto. */
    protected function aplicarOrdenDelSelector(): void
    {
        $columna = data_get($this->tableFilters, FiltroDeOrden::NOMBRE.'.columna');

        if (blank($columna)) {
            $this->tableSortColumn = null;
            $this->tableSortDirection = null;
            $this->tableFilters[FiltroDeOrden::NOMBRE]['direccion'] = null;
        } else {
            $direccion = data_get($this->tableFilters, FiltroDeOrden::NOMBRE.'.direccion') === 'desc' ? 'desc' : 'asc';

            $this->tableSortColumn = $columna;
            $this->tableSortDirection = $direccion;
            // Elegir solo la columna implica ascendente: el selector lo muestra.
            $this->tableFilters[FiltroDeOrden::NOMBRE]['direccion'] = $direccion;
        }

        // Filament ya guardó los filtros antes de este ajuste: se guardan de nuevo.
        $this->updatedTableSortColumn();
        $this->guardarFiltrosEnSesion();
        $this->resetPage();
    }

    protected function guardarFiltrosEnSesion(): void
    {
        if ($this->getTable()->persistsFiltersInSession()) {
            session()->put($this->getTableFiltersSessionKey(), $this->tableFilters);
        }
    }
}
