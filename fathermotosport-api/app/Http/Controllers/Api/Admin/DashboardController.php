<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * KPIs: ventas del mes, pedidos por estado, clientes nuevos, stock bajo.
     */
    public function stats(): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();

        $salesThisMonth = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total');

        $ordersByStatus = Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $newCustomers = User::whereHas('role', fn ($q) => $q->where('slug', 'cliente'))
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Stock bajo: variantes cuyo stock no supera el minimum_stock del producto.
        $lowStock = ProductVariant::join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereColumn('product_variants.stock', '<=', 'products.minimum_stock')
            ->where('product_variants.is_active', true)
            ->count();

        return response()->json([
            'sales_this_month' => round((float) $salesThisMonth, 2),
            'orders_total' => Order::count(),
            'orders_by_status' => $ordersByStatus,
            'new_customers' => $newCustomers,
            'low_stock_variants' => $lowStock,
        ]);
    }

    /**
     * Ventas por día de los últimos 30 días.
     */
    public function salesChart(): JsonResponse
    {
        $rows = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as orders'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Top 5 productos más vendidos (por cantidad).
     */
    public function topProducts(): JsonResponse
    {
        $top = OrderItem::join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.slug',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->orderByDesc('units_sold')
            ->take(5)
            ->get();

        return response()->json(['data' => $top]);
    }
}
