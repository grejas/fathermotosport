<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top productos del mes';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = null;

    protected static ?int $defaultPaginationPageOption = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $start = now()->startOfMonth();

        // Subconsultas correlacionadas (sin GROUP BY) → compatibles con ONLY_FULL_GROUP_BY.
        $unitsSub = DB::table('order_items')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0)');

        $revenueSub = DB::table('order_items')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0)');

        return $table
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectSub($unitsSub, 'units_sold')
                    ->selectSub($revenueSub, 'revenue')
                    ->orderByDesc('units_sold')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Producto')->limit(28),
                Tables\Columns\TextColumn::make('category.name')->label('Categoría')->badge(),
                Tables\Columns\TextColumn::make('units_sold')->label('Vendidos')->badge()->color('info'),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Ingresos')
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2)),
            ])
            ->paginated(false);
    }
}
