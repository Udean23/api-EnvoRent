<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{

    public function creditCard(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount_paid' => 'required|numeric',
            'payment_for' => 'required|in:booking,fine',
            'card_number' => 'required|string',
            'expiry' => 'required|string',
            'bank_name' => 'required|string',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'payment_for' => $validated['payment_for'],
            'order_id' => 'CC-' . strtoupper($validated['payment_for']) . '-' . Str::random(12),
            'gross_amount' => $validated['amount_paid'],
            'transaction_status' => 'settlement',
            'payment_type' => 'credit_card',
            'midtrans_transaction_id' => 'CC-' . strtoupper($validated['payment_for']) . '-' . Str::random(12),
            'paid_at' => now(),
            'raw_response' => [
                'note' => 'Paid online via simulated credit card',
                'card_number' => substr_replace($validated['card_number'], '********', 4, 8),
                'expiry' => $validated['expiry'],
                'bank_name' => $validated['bank_name']
            ]
        ]);

        if ($validated['payment_for'] === 'booking') {
            $transaction->update(['status' => 'in_use']);
        } elseif ($validated['payment_for'] === 'fine') {
            $transaction->update(['fine_amount' => 0, 'status' => 'done']);
        }

        return response()->json([
            'message' => 'Credit Card payment processed successfully',
            'payment' => $payment
        ]);
    }

    public function status($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)->first();
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);
        return response()->json($payment);
    }

    public function offline(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount_paid' => 'required|numeric',
            'payment_for' => 'required|in:booking,fine',
            'card_number' => 'nullable|string',
            'expiry' => 'nullable|string',
            'bank_name' => 'nullable|string',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'payment_for' => $validated['payment_for'],
            'order_id' => 'OFFLINE-' . strtoupper($validated['payment_for']) . '-' . Str::random(12),
            'gross_amount' => $validated['amount_paid'],
            'transaction_status' => 'settlement',
            'payment_type' => 'debit_offline',
            'midtrans_transaction_id' => 'OFFLINE-' . strtoupper($validated['payment_for']) . '-' . Str::random(12),
            'paid_at' => now(),
            'raw_response' => [
                'note' => 'Paid offline via debit/credit',
                'card_number' => $validated['card_number'] ? substr_replace($validated['card_number'], '********', 4, 8) : null,
                'expiry' => $validated['expiry'],
                'bank_name' => $validated['bank_name']
            ]
        ]);

        if ($validated['payment_for'] === 'booking') {
            $transaction->update(['status' => 'in_use']);
        } elseif ($validated['payment_for'] === 'fine') {
            $transaction->update(['fine_amount' => 0, 'status' => 'done']);
        }

        return response()->json([
            'message' => 'Offline payment processed successfully',
            'payment' => $payment
        ]);
    }
}
