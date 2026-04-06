<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{

    public function __construct()
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;
        
        if (config('app.env') !== 'production') {
            \Midtrans\Config::$curlOptions = [
                CURLOPT_SSL_VERIFYPEER => false,
            ];
        }
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::with(['user', 'materials.product', 'materials.bundling'])->findOrFail($validated['transaction_id']);

        if (!in_array($transaction->status, ['accepted', 'pending'])) {
            return response()->json(['message' => 'Transaction status must be accepted or pending'], 422);
        }

        $params = [
            'transaction_details' => [
                'order_id' => 'TRX-' . $transaction->id . '-' . Str::random(5),
                'gross_amount' => (int) $transaction->price,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'item_details' => $transaction->materials->map(function ($m) {
                return [
                    'id' => $m->product_id ?? $m->bundling_id,
                    'price' => (int) ($m->product->price ?? $m->bundling->price ?? 0),
                    'quantity' => $m->quantity,
                    'name' => Str::limit($m->product->name ?? $m->bundling->name ?? 'Item', 50),
                ];
            })->toArray(),
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            Payment::updateOrCreate(
                ['transaction_id' => $transaction->id],
                [
                    'order_id' => $params['transaction_details']['order_id'],
                    'gross_amount' => $transaction->price,
                    'transaction_status' => 'pending',
                    'midtrans_transaction_id' => null,
                ]
            );

            return response()->json([
                'snap_token' => $snapToken,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request)
    {
        $notif = new \Midtrans\Notification();

        $transactionStatus = $notif->transaction_status;
        $type = $notif->payment_type;
        $orderId = $notif->order_id;
        $fraudStatus = $notif->fraud_status;

        $payment = Payment::where('order_id', $orderId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($transactionStatus == 'capture') {
            if ($type == 'credit_card') {
                if ($fraudStatus == 'challenge') {
                    $payment->update(['transaction_status' => 'challenge']);
                } else {
                    $payment->update(['transaction_status' => 'settlement']);
                }
            }
        } elseif ($transactionStatus == 'settlement') {
            $payment->update(['transaction_status' => 'settlement']);
        } elseif (in_array($transactionStatus, ['pending', 'deny', 'expire', 'cancel'])) {
            $payment->update(['transaction_status' => $transactionStatus]);
        }

        if ($payment->transaction_status == 'settlement') {
            $payment->update(['paid_at' => now()]);
            $payment->transaction->update(['status' => 'in_use']);
        }

        return response()->json(['message' => 'Webhook processed']);
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
            'amount_paid' => 'required|numeric'
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'order_id' => 'OFFLINE-' . Str::random(12),
            'gross_amount' => $validated['amount_paid'],
            'transaction_status' => 'settlement',
            'payment_type' => 'cash_offline',
            'midtrans_transaction_id' => 'OFFLINE-' . Str::random(12),
            'paid_at' => now(),
            'raw_response' => ['note' => 'Paid offline']
        ]);

        $transaction->update(['status' => 'in_use']);

        return response()->json([
            'message' => 'Offline payment processed successfully',
            'payment' => $payment
        ]);
    }
}
