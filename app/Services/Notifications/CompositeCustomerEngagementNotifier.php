<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;

/**
 * Birden fazla bildirim adaptörünü sırayla çalıştırır (ör. log + HTTP webhook).
 */
final class CompositeCustomerEngagementNotifier implements CustomerEngagementNotifier
{
    /**
     * @param  list<CustomerEngagementNotifier>  $notifiers
     */
    public function __construct(
        private array $notifiers,
    ) {}

    public function send(string $channel, string $template, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->send($channel, $template, $context);
        }
    }
}
