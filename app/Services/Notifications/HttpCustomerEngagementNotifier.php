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
            ], $timeout, $this->resolveBearerToken('generic'));
        }

        $smsEnabled = (bool) config('customer_engagement.sms.enabled', false);
        $smsEndpoint = config('customer_engagement.sms.endpoint');
        if ($smsEnabled && is_string($smsEndpoint) && $smsEndpoint !== '') {
            $this->postJson($smsEndpoint, [
                'channel' => 'sms',
                'original_channel' => $channel,
                'template' => $template,
                'context' => $context,
            ], $timeout, $this->resolveBearerToken('sms'));
        }

        $waEnabled = (bool) config('customer_engagement.whatsapp.enabled', false);
        $waEndpoint = config('customer_engagement.whatsapp.endpoint');
        if ($waEnabled && is_string($waEndpoint) && $waEndpoint !== '') {
            $this->postJson($waEndpoint, [
                'channel' => 'whatsapp',
                'original_channel' => $channel,
                'template' => $template,
                'context' => $context,
            ], $timeout, $this->resolveBearerToken('whatsapp'));
        }
    }

    private function resolveBearerToken(string $channel): ?string
    {
        if ($channel === 'sms') {
            $t = config('customer_engagement.sms.bearer_token');
            if (is_string($t) && $t !== '') {
                return $t;
            }
        }
        if ($channel === 'whatsapp') {
            $t = config('customer_engagement.whatsapp.bearer_token');
            if (is_string($t) && $t !== '') {
                return $t;
            }
        }

        $generic = config('customer_engagement.http.bearer_token');

        return is_string($generic) && $generic !== '' ? $generic : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postJson(string $url, array $body, int $timeout, ?string $bearerToken = null): void
    {
        try {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return;
        }

        $retry = config('customer_engagement.http.retry');
        $times = is_array($retry) && isset($retry['times']) ? (int) $retry['times'] : 2;
        $sleepMs = is_array($retry) && isset($retry['sleep_ms']) ? (int) $retry['sleep_ms'] : 100;

        $headers = $this->headersForWebhookPayload($json);
        if ($this->idempotencyHeaderEnabled()) {
            $headers['X-Idempotency-Key'] = hash('sha256', $json);
        }

        $pending = Http::timeout($timeout)
            ->retry(max(1, $times), max(0, $sleepMs))
            ->acceptJson()
            ->withHeaders($headers);

        if ($bearerToken !== null) {
            $pending = $pending->withToken($bearerToken);
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

    private function idempotencyHeaderEnabled(): bool
    {
        return (bool) config('customer_engagement.http.idempotency_header', true);
    }
}
