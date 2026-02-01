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
            'id' => $id,
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => "Transaction {$id} updated to status '{$validated['status']}'"
        ], 200);
    }
}
