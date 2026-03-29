<?php

namespace App\Services\Operations;

use App\Contracts\Operations\OperationalNotifier;
use Illuminate\Support\Facades\Log;

final class LogOperationalNotifier implements OperationalNotifier
{
    public function notify(string $event, array $context = []): void
    {
        $channel = (string) config('operations.log_channel');

        Log::channel($channel)->info('operational_notification', array_merge(
            ['event' => $event],
            $context,
        ));
    }
}
