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
        $transactions = Transaction::with('materials', 'user')->get();

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
            'status' => 'nullable|in:pending,completed,cancelled,accepted',
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

        foreach ($validated['materials'] as $index => $material) {
            if (!empty($material['product_id']) && !empty($material['bundling_id'])) {
                return response()->json([
                    'message' => 'Invalid materials data',
                    'errors' => [
                        "materials.$index" => [
                            'Only one of product_id or bundling_id is allowed'
                        ]
                    ]
                ], 422);
            }
        }

        $transaction = Transaction::create([
            'user_id' => $validated['user_id'],
            'price' => $validated['price'],
            'status' => $validated['status'] ?? 'pending',
        ]);

        foreach ($validated['materials'] as $material) {
            $transaction->materials()->create([
                'product_id' => $material['product_id'] ?? null,
                'bundling_id' => $material['bundling_id'] ?? null,
                'quantity' => $material['quantity'],
            ]);
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'description' => 'Created a new transaction',
            'activity_type' => 'crud',
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'transaction' => $transaction->load([
                'materials.product',
                'materials.bundling'
            ])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction, $id)
    {
        $transaction = Transaction::with('materials', 'user')->find($id);

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
    public function destroy(Transaction $transaction, $id)
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found'
            ], 404);
        }

        $transaction->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Deleted a transaction',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'Transaction deleted successfully'
        ]);
    }
}
