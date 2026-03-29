<?php

use App\Services\Notifications\LogCustomerEngagementNotifier;
use Illuminate\Support\Facades\Log;

test('log customer engagement redacts sensitive context keys', function () {
    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('customer_engagement', Mockery::on(function (array $payload): bool {
        $ctx = $payload['context'] ?? [];

        return ($ctx['webhook_token'] ?? null) === '[redacted]'
            && ($ctx['safe_label'] ?? null) === 'visible'
            && is_array($ctx['nested'] ?? null)
            && ($ctx['nested']['client_secret'] ?? null) === '[redacted]'
            && ($ctx['nested']['id'] ?? null) === 42;
    }));

    config(['customer_engagement.log_channel' => 'single']);

    (new LogCustomerEngagementNotifier)->send('logistics', 'shipment.dispatched', [
        'webhook_token' => 'must-not-appear',
        'safe_label' => 'visible',
        'nested' => [
            'client_secret' => 'also-hidden',
            'id' => 42,
        ],
    ]);
});
