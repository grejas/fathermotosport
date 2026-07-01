<?php

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'Stock bajo';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductVariant::query()
                    ->with('product')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->whereColumn('product_variants.stock', '<=', 'products.minimum_stock')
                    ->where('product_variants.is_active', true)
                    ->select('product_variants.*')
                    ->orderBy('product_variants.stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Producto')->limit(30),
                Tables\Columns\TextColumn::make('variant')
                    ->label('Variante')
                    ->getStateUsing(fn (ProductVariant $r) => trim(($r->size ?? '') . ' ' . ($r->color ?? '')) ?: $r->sku),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock actual')
                    ->badge()
                    ->color(fn ($state) => $state == 0 ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('product.minimum_stock')->label('Stock mínimo'),
            ])
            ->recordClasses(fn (ProductVariant $r) => $r->stock == 0 ? 'bg-danger-50 dark:bg-danger-950/30' : null)
            ->paginated(false);
    }
}
