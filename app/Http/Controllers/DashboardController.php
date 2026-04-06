<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $thisMonth = $now->month;
        $thisYear = $now->year;
        $lastMonth = $now->copy()->subMonth()->month;
        $lastMonthYear = $now->copy()->subMonth()->year;

        $totalPenjualan = Transaction::where('status', 'done')
            ->selectRaw('SUM(price + fine_amount) as total')
            ->value('total') ?? 0;
        
        $revenueThisMonth = Transaction::where('status', 'done')
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->selectRaw('SUM(price + fine_amount) as total')
            ->value('total') ?? 0;

        $revenueLastMonth = Transaction::where('status', 'done')
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->selectRaw('SUM(price + fine_amount) as total')
            ->value('total') ?? 0;

        $revenueGrowth = 0;
        if ($revenueLastMonth > 0) {
            $revenueGrowth = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        }

        $target = 50000000;
        $salesProgress = min(100, ($revenueThisMonth / $target) * 100);

        $totalProduk = Product::count();
        $totalPelanggan = User::where('role', 'user')->count();
        $totalPesanan = Transaction::count();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed Dashboard',
            'activity_type' => 'system'
        ]);

        $weeklySales = [];
        $weeklyLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sum = Transaction::where('status', 'done')
                ->whereDate('created_at', $date)
                ->selectRaw('SUM(price + fine_amount) as total')
                ->value('total') ?? 0;
            $weeklySales[] = $sum;
            $weeklyLabels[] = $date->isoFormat('ddd');
        }

        $yearlySales = [];
        for ($i = 1; $i <= 12; $i++) {
            $sum = Transaction::where('status', 'done')
                ->whereYear('created_at', $thisYear)
                ->whereMonth('created_at', $i)
                ->selectRaw('SUM(price + fine_amount) as total')
                ->value('total') ?? 0;
            $yearlySales[] = $sum;
        }

        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'customer' => $t->user->name ?? 'Unknown',
                    'amount' => $t->price + $t->fine_amount,
                    'status' => $t->status,
                    'time' => $t->created_at->diffForHumans()
                ];
            });

        $categorySales = Category::select('categories.name', DB::raw('SUM(transaction_materials.quantity) as total_sold'))
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->join('transaction_materials', 'products.id', '=', 'transaction_materials.product_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_materials.transaction_id')
            ->where('transactions.status', 'done')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        $produkTerlaris = Product::join('transaction_materials', 'products.id', '=', 'transaction_materials.product_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_materials.transaction_id')
            ->where('transactions.status', 'done')
            ->selectRaw('products.id, products.name, products.image, SUM(transaction_materials.quantity) as total_sold')
            ->groupBy('products.id', 'products.name', 'products.image')
            ->orderByDesc('total_sold')
            ->take(4)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'img' => $product->image ? url('storage/' . $product->image) : 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&q=80&w=400',
                    'sold' => $product->total_sold ?? 0,
                    'rating' => 5
                ];
            });


        $allCompletedRentals = Transaction::with(['user', 'materials.product', 'materials.bundling'])
            ->where('status', 'done')
            ->latest()
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'customer' => $t->user->name ?? 'Unknown',
                    'date' => $t->created_at->format('d M Y'),
                    'total' => $t->price + $t->fine_amount,
                    'items' => $t->materials->map(function ($tm) {
                        $itemName = $tm->product->name ?? $tm->bundling->name ?? 'Item Unknown';
                        return $itemName . ' (' . $tm->quantity . 'x)';
                    })->join(', ')
                ];
            });

        return response()->json([
            'total_penjualan' => $totalPenjualan,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_growth' => round($revenueGrowth, 1),
            'sales_progress' => round($salesProgress, 1),
            'total_produk' => $totalProduk,
            'total_pelanggan' => $totalPelanggan,
            'total_pesanan' => $totalPesanan,
            'weekly_sales' => $weeklySales,
            'weekly_labels' => $weeklyLabels,
            'yearly_sales' => $yearlySales,
            'recent_transactions' => $recentTransactions,
            'all_completed_rentals' => $allCompletedRentals,
            'category_sales' => [
                'labels' => $categorySales->pluck('name'),
                'series' => $categorySales->pluck('total_sold')->map(fn($v) => (int)$v)
            ],
            'produk_terlaris' => $produkTerlaris
        ]);
    }
}
