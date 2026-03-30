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

        // Total Penjualan (Status Completed/Accepted)
        $totalPenjualan = Transaction::whereIn('status', ['completed', 'accepted'])->sum('price');
        
        // Revenue This Month
        $revenueThisMonth = Transaction::whereIn('status', ['completed', 'accepted'])
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('price');

        // Revenue Last Month
        $revenueLastMonth = Transaction::whereIn('status', ['completed', 'accepted'])
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->sum('price');

        // Revenue Growth Calculation
        $revenueGrowth = 0;
        if ($revenueLastMonth > 0) {
            $revenueGrowth = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        }

        // Sales Progress (Target 50.000.000)
        $target = 50000000;
        $salesProgress = min(100, ($revenueThisMonth / $target) * 100);

        // Total Produk
        $totalProduk = Product::count();

        // Total Pelanggan
        $totalPelanggan = User::where('role', 'user')->count();

        // Total Pesanan
        $totalPesanan = Transaction::count();

        // Log Aktivitas
        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed Dashboard',
            'activity_type' => 'read'
        ]);

        // Weekly Sales Data
        $weeklySales = [];
        $weeklyLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sum = Transaction::whereIn('status', ['completed', 'accepted'])
                ->whereDate('created_at', $date)
                ->sum('price');
            $weeklySales[] = $sum;
            $weeklyLabels[] = $date->isoFormat('ddd');
        }

        // Yearly Sales Data
        $yearlySales = [];
        for ($i = 1; $i <= 12; $i++) {
            $sum = Transaction::whereIn('status', ['completed', 'accepted'])
                ->whereYear('created_at', $thisYear)
                ->whereMonth('created_at', $i)
                ->sum('price');
            $yearlySales[] = $sum;
        }

        // Recent Transactions
        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'customer' => $t->user->name ?? 'Unknown',
                    'amount' => $t->price,
                    'status' => $t->status,
                    'time' => $t->created_at->diffForHumans()
                ];
            });

        // Category Distribution (Top 5)
        $categorySales = Category::select('categories.name', DB::raw('count(transaction_materials.id) as total_sold'))
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->join('transaction_materials', 'products.id', '=', 'transaction_materials.product_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        // Produk Terlaris
        $produkTerlaris = Product::withSum('transactionMaterials', 'quantity')
            ->orderByDesc('transaction_materials_sum_quantity')
            ->take(4)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'img' => $product->image ? url('storage/' . $product->image) : 'https://source.unsplash.com/400x300/?' . urlencode($product->name),
                    'sold' => $product->transaction_materials_sum_quantity ?? 0,
                    'rating' => 5
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
            'category_sales' => [
                'labels' => $categorySales->pluck('name'),
                'series' => $categorySales->pluck('total_sold')
            ],
            'produk_terlaris' => $produkTerlaris
        ]);
    }
}
