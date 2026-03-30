<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

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

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'order_id' => 'ORDER-' . time(),
            'gross_amount' => $transaction->price,
            'transaction_status' => 'pending',
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => $payment->gross_amount,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'enabled_payments' => [
                'bank_transfer',
                'gopay',
                'shopeepay',
                'qris',
                'credit_card',
                'alfamart',
                'indomaret'
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

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
            'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
            'raw_response' => $payload,
            'paid_at' => in_array($payload['transaction_status'], ['settlement', 'capture'])
                ? now()
                : null,
        ]);

        if (in_array($payload['transaction_status'], ['settlement', 'capture'])) {
            $transaction = $payment->transaction;
            $transaction->update(['status' => 'done']);

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
}
