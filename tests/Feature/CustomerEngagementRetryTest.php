<?php

use App\Services\Notifications\HttpCustomerEngagementNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/engage',
        'customer_engagement.http.timeout_seconds' => 5,
        'customer_engagement.http.retry' => ['times' => 2, 'sleep_ms' => 0],
        'customer_engagement.http.idempotency_header' => false,
    ]);
});

test('retry config is read from customer_engagement.http.retry', function () {
    $retry = config('customer_engagement.http.retry');

    expect($retry)->toBeArray()
        ->and($retry['times'])->toBeGreaterThanOrEqual(1)
        ->and($retry['sleep_ms'])->toBeGreaterThanOrEqual(0);
});

test('notifier succeeds after transient 503 on second attempt', function () {
    Http::fake([
        'https://hooks.example.test/engage' => Http::sequence()
            ->push(['error' => 'service unavailable'], 503)
            ->push(['ok' => true], 200),
    ]);

    $notifier = new HttpCustomerEngagementNotifier;

    // Should not throw even on first 503 — retry kicks in and second attempt succeeds
    expect(fn () => $notifier->send('sms', 'test.template', []))->not->toThrow(Throwable::class);

    Http::assertSentCount(2);
});

test('notifier swallows exception after all retry attempts exhausted', function () {
    // Fail all attempts
    Http::fake([
        'https://hooks.example.test/engage' => Http::sequence()
            ->push(['error' => 'unavailable'], 503)
            ->push(['error' => 'unavailable'], 503)
            ->push(['error' => 'unavailable'], 503),
    ]);

    $notifier = new HttpCustomerEngagementNotifier;

    // Must not propagate — HttpCustomerEngagementNotifier catches Throwable silently
    expect(fn () => $notifier->send('sms', 'test.template', []))->not->toThrow(Throwable::class);
});

test('sms bearer token is sent separately from generic webhook bearer', function () {
    config([
        'customer_engagement.http.endpoint' => '',
        'customer_engagement.http.bearer_token' => 'generic-token',
        'customer_engagement.sms.enabled' => true,
        'customer_engagement.sms.endpoint' => 'https://hooks.example.test/sms',
        'customer_engagement.sms.bearer_token' => 'sms-specific-token',
    ]);

    Http::fake([
        'https://hooks.example.test/sms' => Http::response(['ok' => true], 200),
    ]);

    (new HttpCustomerEngagementNotifier)->send('sms', 'test', []);

    Http::assertSent(function (Request $req): bool {
        return $req->url() === 'https://hooks.example.test/sms'
            && $req->hasHeader('Authorization', 'Bearer sms-specific-token');
    });
});

test('whatsapp bearer token is sent separately from generic webhook bearer', function () {
    config([
        'customer_engagement.http.endpoint' => '',
        'customer_engagement.http.bearer_token' => 'generic-token',
        'customer_engagement.whatsapp.enabled' => true,
        'customer_engagement.whatsapp.endpoint' => 'https://hooks.example.test/wa',
        'customer_engagement.whatsapp.bearer_token' => 'wa-specific-token',
    ]);

    Http::fake([
        'https://hooks.example.test/wa' => Http::response(['ok' => true], 200),
    ]);

    (new HttpCustomerEngagementNotifier)->send('whatsapp', 'test', []);

    Http::assertSent(function (Request $req): bool {
        return $req->url() === 'https://hooks.example.test/wa'
            && $req->hasHeader('Authorization', 'Bearer wa-specific-token');
    });
});
