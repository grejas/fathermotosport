<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = now();
        $startMonth = $now->copy()->startOfMonth();
        $startPrevMonth = $now->copy()->subMonth()->startOfMonth();
        $endPrevMonth = $now->copy()->subMonth()->endOfMonth();

        // Ventas del mes (pagadas)
        $salesMonth = (float) Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $startMonth)->sum('total');
        $salesPrev = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startPrevMonth, $endPrevMonth])->sum('total');
        $salesDiff = $this->percentDiff($salesMonth, $salesPrev);

        // Pedidos del mes
        $ordersMonth = Order::where('created_at', '>=', $startMonth)->count();
        $ordersPrev = Order::whereBetween('created_at', [$startPrevMonth, $endPrevMonth])->count();

        // Clientes nuevos del mes
        $customersMonth = User::whereHas('role', fn ($q) => $q->where('slug', 'cliente'))
            ->where('created_at', '>=', $startMonth)->count();

        // Stock bajo
        $lowStock = ProductVariant::join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereColumn('product_variants.stock', '<=', 'products.minimum_stock')
            ->where('product_variants.is_active', true)
            ->count();

        return [
            Stat::make('Ventas del mes', '$' . number_format($salesMonth, 2))
                ->description($salesDiff['label'])
                ->descriptionIcon($salesDiff['icon'])
                ->color($salesDiff['color'])
                ->icon('heroicon-o-banknotes'),

            Stat::make('Pedidos del mes', $ordersMonth)
                ->description("Mes anterior: {$ordersPrev}")
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('info'),

            Stat::make('Clientes nuevos', $customersMonth)
                ->description('Registrados este mes')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('success'),

            Stat::make('Stock bajo', $lowStock)
                ->description($lowStock > 0 ? 'Variantes por reponer' : 'Inventario saludable')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'success'),
        ];
    }

    /** Calcula la variación porcentual y devuelve etiqueta/ícono/color. */
    private function percentDiff(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return [
                'label' => $current > 0 ? 'Sin datos del mes anterior' : 'Sin ventas',
                'icon' => 'heroicon-o-minus',
                'color' => 'gray',
            ];
        }
        $diff = (($current - $previous) / $previous) * 100;
        $up = $diff >= 0;
        return [
            'label' => sprintf('%s%.1f%% vs mes anterior', $up ? '+' : '', $diff),
            'icon' => $up ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down',
            'color' => $up ? 'success' : 'danger',
        ];
    }
}
