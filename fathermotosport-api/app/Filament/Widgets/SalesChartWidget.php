<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ventas — últimos 30 días';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
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
