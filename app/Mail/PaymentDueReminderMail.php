<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<int, Order>  $orders */
    public function __construct(
        public readonly array $orders,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Payment due reminder — :count orders', ['count' => count($this->orders)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-due-reminder',
        );
    }
}
