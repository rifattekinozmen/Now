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

test('http customer engagement notifier posts to sms and whatsapp endpoints when configured', function () {
    config([
        'customer_engagement.http.endpoint' => '',
        'customer_engagement.http.timeout_seconds' => 5,
        'customer_engagement.sms.enabled' => true,
        'customer_engagement.sms.endpoint' => 'https://hooks.example.test/sms',
        'customer_engagement.whatsapp.enabled' => true,
        'customer_engagement.whatsapp.endpoint' => 'https://hooks.example.test/wa',
    ]);

    Http::fake([
        'https://hooks.example.test/sms' => Http::response(['ok' => true], 200),
        'https://hooks.example.test/wa' => Http::response(['ok' => true], 200),
    ]);

    $notifier = new HttpCustomerEngagementNotifier;
    $notifier->send('logistics', 'shipment.dispatched', ['shipment_id' => 3]);

    Http::assertSentCount(2);
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://hooks.example.test/sms'
            && $request['channel'] === 'sms'
            && $request['template'] === 'shipment.dispatched';
    });
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://hooks.example.test/wa'
            && $request['channel'] === 'whatsapp';
    });
});
