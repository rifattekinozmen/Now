<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FuelPriceWeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public readonly array $summary,
        public readonly string $weekLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Weekly fuel price report — :week', ['week' => $this->weekLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fuel-price-weekly-report',
        );
    }
}
