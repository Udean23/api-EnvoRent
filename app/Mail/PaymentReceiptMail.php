<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Payment $payment,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $paymentType = $this->payment->payment_for === 'booking' ? 'Pemesanan' : 'Denda';
        
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Nota Pembayaran - ' . $paymentType . ' #' . $this->payment->transaction_id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $transaction = $this->payment->transaction;
        $user = $transaction->user;

        $paymentType = $this->payment->payment_for === 'booking' ? 'Pemesanan' : 'Denda';
        
        return new Content(
            view: 'emails.payment_receipt',
            with: [
                'payment' => $this->payment,
                'transaction' => $transaction,
                'user' => $user,
                'paymentType' => $paymentType,
                'formattedAmount' => 'Rp ' . number_format($this->payment->gross_amount, 0, ',', '.'),
                'paymentDate' => $this->payment->paid_at->format('d F Y H:i'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
