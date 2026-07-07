<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ventas — últimos 30 días';

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        // La serie diaria se cachea 300s (clave por hora) para acelerar el dashboard.
        [$labels, $values] = Cache::remember('admin_sales_chart_' . now()->format('Y-m-d-H'), 300, function () {
            $start = now()->subDays(29)->startOfDay();

            // Ventas pagadas agrupadas por día.
            $rows = Order::where('payment_status', 'paid')
                ->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as d, SUM(total) as t')
                ->groupBy('d')
                ->pluck('t', 'd');

            $labels = [];
            $values = [];
            for ($i = 0; $i < 30; $i++) {
                $date = $start->copy()->addDays($i)->format('Y-m-d');
                $labels[] = Carbon::parse($date)->format('d/m');
                $values[] = round((float) ($rows[$date] ?? 0), 2);
            }

            return [$labels, $values];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Ventas ($)',
                    'data' => $values,
                    'borderColor' => '#E8001D',
                    'backgroundColor' => 'rgba(232,0,29,0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
