<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transaction::with([
            'materials.product',
            'materials.bundling.materials.product',
            'user'
        ])->get();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed all transactions',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'price' => 'required|numeric|min:0',
            'status' => 'nullable|in:pending,completed,cancelled,accepted,done,returning,returned,in_use,in_progress',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'materials' => 'required|array|min:1',
            'materials.*.product_id' => [
                'nullable',
                'exists:products,id',
                'required_without:materials.*.bundling_id',
            ],
            'materials.*.bundling_id' => [
                'nullable',
                'exists:bundlings,id',
                'required_without:materials.*.product_id',
            ],
            'materials.*.quantity' => 'required|integer|min:1',
        ]);

        foreach ($validated['materials'] as $index => $mat) {
            if (!empty($mat['product_id'])) {
                $product = \App\Models\Product::find($mat['product_id']);
                if ($product->stock < $mat['quantity']) {
                    return response()->json(['message' => "Stok produk '{$product->name}' tidak mencukupi"], 422);
                }
            } elseif (!empty($mat['bundling_id'])) {
                $bundling = \App\Models\Bundling::with('materials.product')->find($mat['bundling_id']);
                foreach ($bundling->materials as $bm) {
                    $requiredQty = $bm->quantity * $mat['quantity'];
                    if ($bm->product->stock < $requiredQty) {
                        return response()->json(['message' => "Stok produk '{$bm->product->name}' dalam bundling tidak mencukupi"], 422);
                    }
                }
            }
        }

        $transaction = Transaction::create([
            'user_id' => $validated['user_id'],
            'price' => $validated['price'],
            'status' => $validated['status'] ?? 'pending',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        foreach ($validated['materials'] as $mat) {
            $transaction->materials()->create([
                'product_id' => $mat['product_id'] ?? null,
                'bundling_id' => $mat['bundling_id'] ?? null,
                'quantity' => $mat['quantity'],
            ]);

            if (!empty($mat['product_id'])) {
                \App\Models\Product::find($mat['product_id'])->decrement('stock', $mat['quantity']);
            } elseif (!empty($mat['bundling_id'])) {
                $bundling = \App\Models\Bundling::with('materials')->find($mat['bundling_id']);
                foreach ($bundling->materials as $bm) {
                    \App\Models\Product::find($bm->product_id)->decrement('stock', $bm->quantity * $mat['quantity']);
                }
            }
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'description' => 'Created a new transaction and reserved stock',
            'activity_type' => 'crud',
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'transaction' => $transaction->load(['materials.product', 'materials.bundling'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction, $id)
    {
        $transaction = Transaction::with([
            'materials.product',
            'materials.bundling.materials.product',
            'user'
        ])->find($id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found'
            ], 404);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed a transaction',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'transaction' => $transaction
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function requestReturn($id)
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if (!in_array($transaction->status, ['in_use', 'done'])) {
            return response()->json(['message' => 'Can only return active transactions'], 400);
        }

        $transaction->update(['status' => 'in_progress']);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => "Requested return for transaction {$id}",
            'activity_type' => 'crud'
        ]);

        return response()->json(['message' => 'Return request submitted successfully']);
    }
}
