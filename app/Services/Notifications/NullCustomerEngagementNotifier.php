<?php

namespace App\Services\Notifications;

use App\Contracts\CustomerEngagementNotifier;

final class NullCustomerEngagementNotifier implements CustomerEngagementNotifier
{
    public function send(string $channel, string $template, array $context = []): void {}
}
