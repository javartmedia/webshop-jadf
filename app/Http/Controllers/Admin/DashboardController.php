<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('grand_total'),
            'total_products' => Product::count(),
            'total_customers' => User::whereHas('role', function ($q) {
                $q->where('slug', 'customer');
            })->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'low_stock_products' => Product::where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count(),
            'out_of_stock_products' => Product::where('stock_quantity', 0)->count(),
        ];

        // Recent orders
        $recentOrders = Order::with('user')
            ->latest()
            ->limit(10)
            ->get();

        // Top selling products
        $topProducts = Product::active()
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();

        // Revenue chart data (last 30 days)
        $revenueData = Order::where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(paid_at) as date, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        return view('admin.dashboard', compact('stats', 'recentOrders', 'topProducts', 'revenueData'));
    }
}
