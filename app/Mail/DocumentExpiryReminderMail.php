<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{entity:string, plate?:string, name?:string, expires_on:string, days_remaining:int}  $context
     */
    public function __construct(
        public readonly array $context,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Document expiry reminder — :days days remaining', ['days' => $this->context['days_remaining']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-expiry-reminder',
        );
    }
}
