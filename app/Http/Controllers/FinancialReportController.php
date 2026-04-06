<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    public function index()
    {
        $reportStatuses = ['done'];
        $transactions = Transaction::whereIn('status', $reportStatuses)->get();
        
        $totalIncome = $transactions->sum('price');
        $totalFine = $transactions->sum('fine_amount');
        
        $bestSellers = Product::select('products.*')
            ->join('transaction_materials', 'products.id', '=', 'transaction_materials.product_id')
            ->join('transactions', 'transactions.id', '=', 'transaction_materials.transaction_id')
            ->whereIn('transactions.status', $reportStatuses)
            ->selectRaw('products.id, products.name, SUM(transaction_materials.quantity) as total_sold')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->take(10)
            ->get();

        $monthlyRevenue = Transaction::whereIn('status', $reportStatuses)
            ->whereYear('created_at', now()->year)
            ->selectRaw('MONTH(created_at) as month, SUM(price + fine_amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'summary' => [
                'total_income' => $totalIncome,
                'total_fine' => $totalFine,
                'grand_total' => $totalIncome + $totalFine,
                'transaction_count' => $transactions->count(),
            ],
            'best_sellers' => $bestSellers,
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }
}
