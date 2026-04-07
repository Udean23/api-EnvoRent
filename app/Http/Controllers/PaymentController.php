<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;
use Xendit\PaymentRequest\PaymentMethodParameters;
use Xendit\PaymentRequest\PaymentMethodType;
use Xendit\PaymentRequest\PaymentMethodReusability;
use Xendit\PaymentRequest\VirtualAccountParameters;
use Xendit\PaymentRequest\VirtualAccountChannelCode;
use Xendit\PaymentRequest\VirtualAccountChannelProperties;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
    public function __construct()
    {
        Configuration::setXenditKey(config('xendit.api_key'));
    }

    /**
     * Create a configured Guzzle client with SSL verification
     */
    private function createHttpClient()
    {
        $caCertPath = base_path('cacert.pem');

        return new Client([
            'verify' => $caCertPath,
            'timeout' => 30,
        ]);
    }

    /**
     * Send payment receipt email to customer
     */
    private function sendPaymentReceiptEmail(Payment $payment)
    {
        try {
            // Make sure payment has paid_at set
            if (!$payment->paid_at) {
                $payment->update(['paid_at' => now()]);
            }

            // Send email via queue
            Mail::to($payment->transaction->user->email)
                ->queue(new PaymentReceiptMail($payment));

            \Log::info('Payment receipt email queued', [
                'payment_id' => $payment->id,
                'email' => $payment->transaction->user->email,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send payment receipt email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create payment invoice using Xendit
     */
    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount_paid' => 'required|numeric|min:1',
            'payment_for' => 'required|in:booking,fine',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $apiInstance = new InvoiceApi($this->createHttpClient());

            $create_invoice_request = new CreateInvoiceRequest([
                'external_id' => strtoupper($validated['payment_for']) . '-' . $transaction->id . '-' . time(),
                'amount' => (int) $validated['amount_paid'],
                'payer_email' => $validated['customer_email'],
                'description' => 'Payment for ' . $validated['payment_for'] . ' - Transaction #' . $transaction->id,
                'invoice_duration' => 3600, // 1 hour expiration
                'currency' => 'IDR',
                'reminder_time' => 1
            ]);

            $invoice = $apiInstance->createInvoice($create_invoice_request);

            $payment = Payment::create([
                'transaction_id' => $transaction->id,
                'payment_for' => $validated['payment_for'],
                'order_id' => $invoice->getId(),
                'gross_amount' => $validated['amount_paid'],
                'transaction_status' => 'pending',
                'payment_type' => 'xendit_invoice',
                'xendit_invoice_id' => $invoice->getId(),
                'xendit_transaction_id' => $invoice->getId(),
                'raw_response' => $invoice
            ]);

            return response()->json([
                'message' => 'Invoice created successfully',
                'payment_url' => $invoice->getInvoiceUrl(),
                'payment' => $payment,
                'invoice_id' => $invoice->getId()
            ]);
        } catch (\Xendit\XenditSdkException $e) {
            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Credit card payment - redirects to Xendit invoice
     */
    public function creditCard(Request $request)
    {
        return $this->createInvoice($request);
    }

    /**
     * Check payment status
     */
    public function status($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)->latest()->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        try {
            $xenditStatus = 'UNKNOWN';

            if ($payment->payment_type === 'xendit_invoice' && $payment->xendit_invoice_id) {
                $apiInstance = new InvoiceApi($this->createHttpClient());
                $invoiceDetails = $apiInstance->getInvoiceById($payment->xendit_invoice_id);
                $xenditStatus = $invoiceDetails->getStatus();

                if ($xenditStatus === 'PAID') {
                    $payment->update([
                        'transaction_status' => 'settlement',
                        'paid_at' => now()
                    ]);

                    $transaction = $payment->transaction;
                    if ($payment->payment_for === 'booking') {
                        $transaction->update(['status' => 'in_use']);
                    } elseif ($payment->payment_for === 'fine') {
                        $transaction->update(['fine_amount' => 0, 'status' => 'done']);
                    }

                    // Send payment receipt email
                    $this->sendPaymentReceiptEmail($payment);
                } else {
                    $payment->update(['transaction_status' => $xenditStatus]);
                }
            } elseif ($payment->payment_type === 'xendit_virtual_account' && $payment->xendit_transaction_id) {
                $prApiInstance = new PaymentRequestApi($this->createHttpClient());
                $paymentRequest = $prApiInstance->getPaymentRequestByID($payment->xendit_transaction_id);
                $xenditStatus = $paymentRequest->getStatus();

                if ($xenditStatus === 'SUCCEEDED') {
                    $payment->update([
                        'transaction_status' => 'settlement',
                        'paid_at' => now()
                    ]);

                    $transaction = $payment->transaction;
                    if ($payment->payment_for === 'booking') {
                        $transaction->update(['status' => 'in_use']);
                    } elseif ($payment->payment_for === 'fine') {
                        $transaction->update(['fine_amount' => 0, 'status' => 'done']);
                    }

                    // Send payment receipt email
                    $this->sendPaymentReceiptEmail($payment);
                } else {
                    $payment->update(['transaction_status' => $xenditStatus]);
                }
            }

            return response()->json([
                'payment' => $payment,
                'xendit_status' => $xenditStatus,
                'transaction_status' => $payment->transaction_status
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment status check error', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'payment_type' => $payment->payment_type
            ]);

            return response()->json([
                'message' => 'Failed to check status',
                'error' => $e->getMessage(),
                'payment' => $payment
            ], 500);
        }
    }

    /**
     * Webhook handler for Xendit payment notifications
     */
    public function webhook(Request $request)
    {
        $data = $request->all();

        try {
            $callbackToken = $request->header('X-Callback-Token');
            $expectedToken = config('xendit.webhook_token');

            if ($expectedToken && $callbackToken !== $expectedToken) {
                \Log::warning('Invalid webhook token', ['received' => $callbackToken]);
                return response()->json(['message' => 'Invalid webhook token'], 401);
            }

            if (!isset($data['id'])) {
                return response()->json(['message' => 'Invalid webhook data'], 400);
            }

            $payment = Payment::where('xendit_invoice_id', $data['id'])
                ->orWhere('xendit_transaction_id', $data['id'])
                ->first();

            if (!$payment) {
                \Log::warning('Payment not found for webhook', ['xendit_id' => $data['id']]);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $status = 'pending';
            if (isset($data['status'])) {
                if ($data['status'] === 'PAID' || $data['status'] === 'COMPLETED') {
                    $status = 'settlement';
                } elseif ($data['status'] === 'EXPIRED') {
                    $status = 'expired';
                } elseif ($data['status'] === 'FAILED') {
                    $status = 'failed';
                }
            }

            $payment->update([
                'transaction_status' => $status,
                'raw_response' => array_merge($payment->raw_response ?? [], $data)
            ]);

            if ($status === 'settlement' && !$payment->paid_at) {
                $payment->update(['paid_at' => now()]);

                $transaction = $payment->transaction;
                if ($payment->payment_for === 'booking') {
                    $transaction->update(['status' => 'in_use']);
                } elseif ($payment->payment_for === 'fine') {
                    $transaction->update(['fine_amount' => 0, 'status' => 'done']);
                }

                // Send payment receipt email
                $payment = $payment->fresh();
                $this->sendPaymentReceiptEmail($payment);
            }

            \Log::info('Webhook processed successfully', [
                'payment_id' => $payment->id,
                'status' => $status,
                'xendit_id' => $data['id']
            ]);

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            \Log::error('Xendit webhook error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return response()->json(['message' => 'Error processing webhook'], 500);
        }
    }

    /**
     * Virtual account payment
     */
    public function virtualAccount(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount_paid' => 'required|numeric|min:1',
            'payment_for' => 'required|in:booking,fine',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string',
            'bank_code' => 'required|in:BCA,BNI,MANDIRI,BRI',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $apiInstance = new PaymentRequestApi($this->createHttpClient());

            $channelCodeMap = [
                'BCA' => VirtualAccountChannelCode::BCA,
                'BNI' => VirtualAccountChannelCode::BNI,
                'MANDIRI' => VirtualAccountChannelCode::MANDIRI,
                'BRI' => VirtualAccountChannelCode::BRI,
            ];

            $channelCode = $channelCodeMap[$validated['bank_code']] ?? VirtualAccountChannelCode::BCA;

            $payment_request_parameters = new PaymentRequestParameters([
                'reference_id' => 'VA-' . strtoupper($validated['payment_for']) . '-' . $transaction->id . '-' . time(),
                'amount' => (int) $validated['amount_paid'],
                'currency' => \Xendit\PaymentRequest\PaymentRequestCurrency::IDR,
                'payment_method' => new PaymentMethodParameters([
                    'type' => PaymentMethodType::VIRTUAL_ACCOUNT,
                    'reusability' => PaymentMethodReusability::ONE_TIME_USE,
                    'virtual_account' => new VirtualAccountParameters([
                        'channel_code' => $channelCode,
                        'channel_properties' => new VirtualAccountChannelProperties([
                            'customer_name' => $validated['customer_name']
                        ])
                    ]),
                ]),
                'description' => 'Payment for ' . $validated['payment_for'] . ' - Transaction #' . $transaction->id,
            ]);

            $paymentRequest = $apiInstance->createPaymentRequest(
                null, 
                null,                 
                $payment_request_parameters
            );

            $payment = Payment::create([
                'transaction_id' => $transaction->id,
                'payment_for' => $validated['payment_for'],
                'order_id' => $paymentRequest->getId(),
                'gross_amount' => $validated['amount_paid'],
                'transaction_status' => 'pending',
                'payment_type' => 'xendit_virtual_account',
                'xendit_invoice_id' => $paymentRequest->getId(),
                'xendit_transaction_id' => $paymentRequest->getId(),
                'raw_response' => $paymentRequest
            ]);

            return response()->json([
                'message' => 'Virtual account created successfully',
                'payment' => $payment,
                'virtual_account_number' => $paymentRequest->getPaymentMethod()['virtual_account']['channel_properties']['account_number'] ?? null,
                'bank_code' => $validated['bank_code'],
                'va_id' => $paymentRequest->getId(),
                'expiration_date' => $paymentRequest->getPaymentMethod()['virtual_account']['channel_properties']['expires_at'] ?? now()->addHours(24)->toISOString()
            ]);
        } catch (\Xendit\XenditSdkException $e) {
            \Log::error('Xendit VA Creation Error', [
                'error' => $e->getMessage(),
                'transaction_id' => $validated['transaction_id'],
                'amount' => $validated['amount_paid']
            ]);

            return response()->json([
                'message' => 'Failed to create virtual account',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Xendit VA Creation Error', [
                'error' => $e->getMessage(),
                'transaction_id' => $validated['transaction_id'],
                'amount' => $validated['amount_paid']
            ]);

            return response()->json([
                'message' => 'Failed to create virtual account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
