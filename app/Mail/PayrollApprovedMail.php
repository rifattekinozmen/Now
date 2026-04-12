<?php

namespace App\Mail;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payroll $payroll,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your payroll has been approved'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payroll-approved',
        );
    }
}
