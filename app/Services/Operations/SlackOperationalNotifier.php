<?php

namespace App\Services\Operations;

use App\Contracts\Operations\OperationalNotifier;
use Illuminate\Support\Facades\Http;

/**
 * Incoming Webhook ile Slack’e kısa metin (yapılandırma yoksa no-op).
 */
final class SlackOperationalNotifier implements OperationalNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $event, array $context = []): void
    {
        $url = config('operations.slack_webhook_url');

        if (! is_string($url) || $url === '') {
            return;
        }

        $payload = json_encode($context, JSON_UNESCAPED_UNICODE);
        $text = '['.$event.'] '.($payload !== false ? $payload : '{}');

        Http::timeout((int) config('operations.slack_timeout_seconds', 5))
            ->post($url, ['text' => $text]);
    }
}
