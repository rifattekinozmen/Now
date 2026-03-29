<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;
use Illuminate\Support\Facades\Log;

final class LogCustomerEngagementNotifier implements CustomerEngagementNotifier
{
    public function send(string $channel, string $template, array $context = []): void
    {
        Log::channel((string) config('customer_engagement.log_channel', 'single'))->info('customer_engagement', [
            'channel' => $channel,
            'template' => $template,
            'context' => $context,
        ]);
    }
}
