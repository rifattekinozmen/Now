<?php

use App\Services\Operations\SlackOperationalNotifier;
use Illuminate\Support\Facades\Http;

test('slack notifier posts json payload when webhook url set', function () {
    Http::fake();

    config(['operations.slack_webhook_url' => 'https://hooks.slack.com/services/TEST/WEBHOOK']);

    $notifier = new SlackOperationalNotifier;
    $notifier->notify('logistics.test', ['order_id' => 9]);

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://hooks.slack.com/services/TEST/WEBHOOK'
            && isset($data['text'])
            && str_contains((string) $data['text'], 'logistics.test')
            && str_contains((string) $data['text'], 'order_id');
    });
});

test('slack notifier does nothing when webhook url empty', function () {
    Http::fake();

    config(['operations.slack_webhook_url' => '']);

    $notifier = new SlackOperationalNotifier;
    $notifier->notify('logistics.test', []);

    Http::assertNothingSent();
});
