<?php

use App\Services\Notifications\HttpCustomerEngagementNotifier;
use Illuminate\Support\Facades\Http;

test('http customer engagement notifier posts json payload when endpoint set', function () {
    config([
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/engage',
        'customer_engagement.http.timeout_seconds' => 5,
    ]);

    Http::fake([
        'https://hooks.example.test/engage' => Http::response(['ok' => true], 200),
    ]);

    $notifier = new HttpCustomerEngagementNotifier;
    $notifier->send('sms', 'shipment_update', ['shipment_id' => 9]);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://hooks.example.test/engage'
            && $request['channel'] === 'sms'
            && $request['template'] === 'shipment_update'
            && $request['context']['shipment_id'] === 9;
    });
});

test('http customer engagement notifier skips when endpoint empty', function () {
    config(['customer_engagement.http.endpoint' => '']);

    Http::fake();

    $notifier = new HttpCustomerEngagementNotifier;
    $notifier->send('whatsapp', 'ping', []);

    Http::assertNothingSent();
});
