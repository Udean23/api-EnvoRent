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
            'status' => 'required|in:pending,done,declined,in_use,accepted,returning,returned,in_progress,cancelled',
        ]);

        $oldStatus = $transaction->status;
        $newStatus = $validated['status'];

        $transaction->update([
            'status' => $newStatus,
            'returned_at' => in_array($newStatus, ['returned', 'done']) ? now() : $transaction->returned_at
        ]);

        if (in_array($newStatus, ['returned', 'done']) && $transaction->end_date) {
            $endDate = \Carbon\Carbon::parse($transaction->end_date);
            $returnedDate = now();
            if ($returnedDate->greaterThan($endDate)) {
                $daysLate = (int) $endDate->diffInDays($returnedDate, false);
                if ($daysLate > 0) {
                    $transaction->update(['fine_amount' => $daysLate * 50000]);
                }
            }
        }

        $activeStatuses = ['pending', 'accepted', 'in_use', 'returning', 'in_progress'];
        $returnableStatuses = ['done', 'returned', 'declined', 'cancelled'];

        if (in_array($newStatus, $returnableStatuses) && in_array($oldStatus, $activeStatuses)) {
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
            'message' => "Transaction {$id} updated to status '{$newStatus}'"
        ], 200);
    }
}
