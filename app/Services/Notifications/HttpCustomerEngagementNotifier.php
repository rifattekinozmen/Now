<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SMS/WhatsApp sağlayıcısı yerine genel HTTP webhook; gerçek entegrasyon için ara katman.
 */
final class HttpCustomerEngagementNotifier implements CustomerEngagementNotifier
{
    public function send(string $channel, string $template, array $context = []): void
    {
        $endpoint = config('customer_engagement.http.endpoint');
        if (! is_string($endpoint) || $endpoint === '') {
            return;
        }

        try {
            Http::timeout((int) config('customer_engagement.http.timeout_seconds', 10))
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'channel' => $channel,
                    'template' => $template,
                    'context' => $context,
                ]);
        } catch (Throwable) {
            //
        }
    }
}
