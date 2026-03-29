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
            'context' => $this->redactSensitiveContext($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redactSensitiveContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $lower = strtolower($key);
            if (
                str_contains($lower, 'password')
                || str_contains($lower, 'secret')
                || str_contains($lower, 'token')
                || str_contains($lower, 'bearer')
                || str_contains($lower, 'authorization')
            ) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redactSensitiveContext($value);

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
