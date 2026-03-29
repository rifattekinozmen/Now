<?php

namespace App\Contracts;

/**
 * SMS / WhatsApp vb. müşteri veya saha bildirimleri (OperationalNotifier’dan ayrı kanal).
 */
interface CustomerEngagementNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $channel, string $template, array $context = []): void;
}
