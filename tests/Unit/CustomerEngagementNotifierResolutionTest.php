<?php

use App\Contracts\CustomerEngagementNotifier;
use App\Services\Notifications\HttpCustomerEngagementNotifier;
use App\Services\Notifications\LogCustomerEngagementNotifier;
use App\Services\Notifications\NullCustomerEngagementNotifier;

test('customer engagement resolves http notifier when endpoint is configured', function () {
    config([
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/notify',
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => false,
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(HttpCustomerEngagementNotifier::class);
});

test('customer engagement resolves http notifier when sms enabled and sms endpoint set', function () {
    config([
        'customer_engagement.http.endpoint' => null,
        'customer_engagement.sms.enabled' => true,
        'customer_engagement.sms.endpoint' => 'https://sms-bridge.example.test/hook',
        'customer_engagement.whatsapp.enabled' => false,
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(HttpCustomerEngagementNotifier::class);
});

test('customer engagement resolves log notifier when sms flag enabled without sms endpoint', function () {
    config([
        'customer_engagement.http.endpoint' => null,
        'customer_engagement.sms.enabled' => true,
        'customer_engagement.sms.endpoint' => null,
        'customer_engagement.whatsapp.enabled' => false,
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(LogCustomerEngagementNotifier::class);
});

test('customer engagement resolves http notifier when whatsapp enabled and whatsapp endpoint set', function () {
    config([
        'customer_engagement.http.endpoint' => '',
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => true,
        'customer_engagement.whatsapp.endpoint' => 'https://wa-bridge.example.test/hook',
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(HttpCustomerEngagementNotifier::class);
});

test('customer engagement resolves log notifier when whatsapp flag enabled without whatsapp endpoint', function () {
    config([
        'customer_engagement.http.endpoint' => '',
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => true,
        'customer_engagement.whatsapp.endpoint' => '',
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(LogCustomerEngagementNotifier::class);
});

test('customer engagement resolves null notifier when all channels off', function () {
    config([
        'customer_engagement.driver' => 'auto',
        'customer_engagement.http.endpoint' => null,
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => false,
        'customer_engagement.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(NullCustomerEngagementNotifier::class);
});

test('customer engagement driver null forces null notifier even if http endpoint set', function () {
    config([
        'customer_engagement.driver' => 'null',
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/notify',
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(NullCustomerEngagementNotifier::class);
});

test('customer engagement driver log forces log notifier', function () {
    config([
        'customer_engagement.driver' => 'log',
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/notify',
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(LogCustomerEngagementNotifier::class);
});

test('customer engagement driver http forces http notifier', function () {
    config([
        'customer_engagement.driver' => 'http',
        'customer_engagement.http.endpoint' => null,
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => false,
    ]);

    expect(app(CustomerEngagementNotifier::class))->toBeInstanceOf(HttpCustomerEngagementNotifier::class);
});
