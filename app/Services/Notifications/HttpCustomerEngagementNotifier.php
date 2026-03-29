<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Genel HTTP webhook + isteğe bağlı SMS / WhatsApp uçlarına ayrı POST (aynı JSON gövdesi mantığı).
 */
final class HttpCustomerEngagementNotifier implements CustomerEngagementNotifier
{
    public function send(string $channel, string $template, array $context = []): void
    {
        $timeout = (int) config('customer_engagement.http.timeout_seconds', 10);
        $generic = config('customer_engagement.http.endpoint');
        if (is_string($generic) && $generic !== '') {
            $this->postJson($generic, [
                'channel' => $channel,
                'template' => $template,
                'context' => $context,
            ], $timeout);
        }

        $smsEnabled = (bool) config('customer_engagement.sms.enabled', false);
        $smsEndpoint = config('customer_engagement.sms.endpoint');
        if ($smsEnabled && is_string($smsEndpoint) && $smsEndpoint !== '') {
            $this->postJson($smsEndpoint, [
                'channel' => 'sms',
                'original_channel' => $channel,
                'template' => $template,
                'context' => $context,
            ], $timeout);
        }

        $waEnabled = (bool) config('customer_engagement.whatsapp.enabled', false);
        $waEndpoint = config('customer_engagement.whatsapp.endpoint');
        if ($waEnabled && is_string($waEndpoint) && $waEndpoint !== '') {
            $this->postJson($waEndpoint, [
                'channel' => 'whatsapp',
                'original_channel' => $channel,
                'template' => $template,
                'context' => $context,
            ], $timeout);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postJson(string $url, array $body, int $timeout): void
    {
        try {
            Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $body);
        } catch (Throwable) {
            //
        }
    }
}
