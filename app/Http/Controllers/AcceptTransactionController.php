<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class AcceptTransactionController extends Controller
{
    public function accept(Request $request, $id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $validated = request()->validate([
            'id' => 'nullable|integer|exists:transactions,id',
            'status' => 'required|in:pending,done,declined,in_use,accepted',
        ]);

        $transaction->update([
            'status' => $validated['status']
        ]);

        if ($validated['status'] === 'returned') {
            foreach ($transaction->materials as $mat) {
                if ($mat->product_id) {
                    $product = \App\Models\Product::find($mat->product_id);
                    if ($product) {
                        $product->increment('stock', $mat->quantity);
                    }
                } elseif ($mat->bundling_id) {
                    $bundling = \App\Models\Bundling::with('materials')->find($mat->bundling_id);
                    if ($bundling) {
                        foreach ($bundling->materials as $bMat) {
                            $product = \App\Models\Product::find($bMat->product_id);
                            if ($product) {
                                $product->increment('stock', $bMat->quantity * $mat->quantity);
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'message' => "Transaction {$id} updated to status '{$validated['status']}'"
        ], 200);
    }
}
