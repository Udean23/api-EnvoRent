<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        if ($transaction->status !== 'accepted') {
            return response()->json([
                'message' => 'Transaction must be accepted before checkout'
            ], 422);
        }

        $existingPayment = Payment::where('transaction_id', $transaction->id)
            ->whereIn('transaction_status', ['pending', 'settlement', 'capture'])
            ->first();

        if ($existingPayment) {
            return response()->json([
                'message' => 'This transaction already has an active payment',
                'payment' => $existingPayment
            ], 409);
        }

        // Custom Mock Payment Gateway logic
        $snapToken = 'MOCK-' . Str::random(32);
        
        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'order_id' => $snapToken,
            'gross_amount' => $transaction->price,
            'transaction_status' => 'pending',
            'midtrans_transaction_id' => $snapToken,
        ]);

        return response()->json([
            'message' => 'Checkout created',
            'snap_token' => $snapToken,
            'payment' => $payment
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        $payment = Payment::where('order_id', $payload['order_id'] ?? null)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->update([
            'transaction_status' => $payload['transaction_status'] ?? $payment->transaction_status,
            'payment_type' => $payload['payment_type'] ?? null,
            'fraud_status' => $payload['fraud_status'] ?? null,
            'midtrans_transaction_id' => $payload['order_id'] ?? null,
            'raw_response' => $payload,
            'paid_at' => in_array($payload['transaction_status'], ['settlement', 'capture'])
                ? now()
                : null,
        ]);

        if (in_array($payload['transaction_status'], ['settlement', 'capture'])) {
            $transaction = $payment->transaction;
            $transaction->update(['status' => 'in_use']);

            foreach ($transaction->materials as $mat) {
                if ($mat->product_id) {
                    $product = \App\Models\Product::find($mat->product_id);
                    if ($product) {
                        $product->decrement('stock', $mat->quantity);
                    }
                } elseif ($mat->bundling_id) {
                    $bundling = \App\Models\Bundling::with('materials')->find($mat->bundling_id);
                    if ($bundling) {
                        foreach ($bundling->materials as $bMat) {
                            $product = \App\Models\Product::find($bMat->product_id);
                            if ($product) {
                                $product->decrement('stock', $bMat->quantity * $mat->quantity);
                            }
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    public function status($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    public function offline(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount_paid' => 'required|numeric'
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        if (!in_array($transaction->status, ['accepted', 'pending'])) {
            return response()->json(['message' => 'Transaction cannot be paid offline at this status'], 422);
        }

        // Create offline payment
        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'order_id' => 'OFFLINE-' . Str::random(12),
            'gross_amount' => $validated['amount_paid'],
            'transaction_status' => 'settlement',
            'payment_type' => 'cash_offline',
            'midtrans_transaction_id' => 'OFFLINE-' . Str::random(12),
            'paid_at' => now(),
            'raw_response' => ['note' => 'Paid offline via Cashier']
        ]);

        // Update transaction status
        $transaction->update(['status' => 'in_use']);

        // Decrement stock
        foreach ($transaction->materials as $mat) {
            if ($mat->product_id) {
                $product = \App\Models\Product::find($mat->product_id);
                if ($product) { $product->decrement('stock', $mat->quantity); }
            } elseif ($mat->bundling_id) {
                $bundling = \App\Models\Bundling::with('materials')->find($mat->bundling_id);
                if ($bundling) {
                    foreach ($bundling->materials as $bMat) {
                        $product = \App\Models\Product::find($bMat->product_id);
                        if ($product) { $product->decrement('stock', $bMat->quantity * $mat->quantity); }
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Offline payment processed successfully',
            'payment' => $payment
        ]);
    }
}
