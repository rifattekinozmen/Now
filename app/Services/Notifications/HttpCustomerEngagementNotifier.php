<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;
use Illuminate\Support\Facades\Http;
use JsonException;
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
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return;
        }

        $retry = config('customer_engagement.http.retry');
        $times = is_array($retry) && isset($retry['times']) ? (int) $retry['times'] : 2;
        $sleepMs = is_array($retry) && isset($retry['sleep_ms']) ? (int) $retry['sleep_ms'] : 100;

        $pending = Http::timeout($timeout)
            ->retry(max(1, $times), max(0, $sleepMs))
            ->acceptJson()
            ->withHeaders($this->headersForWebhookPayload($json));

        $bearer = config('customer_engagement.http.bearer_token');
        if (is_string($bearer) && $bearer !== '') {
            $pending = $pending->withToken($bearer);
        }

        try {
            $pending->withBody($json, 'application/json')->post($url);
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return array<string, string>
     */
    private function headersForWebhookPayload(string $jsonBody): array
    {
        $secret = config('customer_engagement.http.signature_secret');
        if (! is_string($secret) || $secret === '') {
            return [];
        }

        $hash = hash_hmac('sha256', $jsonBody, $secret);

        return [
            'X-Webhook-Signature' => 'sha256='.$hash,
        ];
    }
}
