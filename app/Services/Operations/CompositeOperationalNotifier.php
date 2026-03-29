<?php

namespace App\Services\Operations;

use App\Contracts\Operations\OperationalNotifier;

/**
 * Birden fazla kanalı (log, Slack vb.) aynı operasyonel olay için sırayla çağırır.
 */
final class CompositeOperationalNotifier implements OperationalNotifier
{
    /**
     * @param  array<int, OperationalNotifier>  $notifiers
     */
    public function __construct(
        private array $notifiers,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $event, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->notify($event, $context);
        }
    }
}
