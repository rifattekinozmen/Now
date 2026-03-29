<?php

use App\Services\Notifications\HttpCustomerEngagementNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('http webhook sends bearer and hmac signature when configured', function () {
    config([
        'customer_engagement.http.endpoint' => 'https://hooks.example.test/webhook',
        'customer_engagement.http.timeout_seconds' => 5,
        'customer_engagement.http.bearer_token' => 'secret-bearer',
        'customer_engagement.http.signature_secret' => 'signing-secret',
        'customer_engagement.http.retry' => ['times' => 1, 'sleep_ms' => 0],
    ]);

    Http::fake([
        'https://hooks.example.test/webhook' => Http::response(['ok' => true], 200),
    ]);

    $notifier = new HttpCustomerEngagementNotifier;
    $notifier->send('logistics', 'shipment.dispatched', ['shipment_id' => 42]);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://hooks.example.test/webhook') {
            return false;
        }
        if (! $request->hasHeader('Authorization', 'Bearer secret-bearer')) {
            return false;
        }
        $sig = $request->header('X-Webhook-Signature')[0] ?? '';
        if (! str_starts_with($sig, 'sha256=')) {
            return false;
        }
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $body, 'signing-secret');
        if ($sig !== $expected) {
            return false;
        }

        return $request->hasHeader('X-Idempotency-Key', hash('sha256', $body));
    });
});

test('sms endpoint uses dedicated bearer when configured', function () {
    config([
        'customer_engagement.sms.enabled' => true,
        'customer_engagement.sms.endpoint' => 'https://sms.example.test/send',
        'customer_engagement.sms.bearer_token' => 'sms-only-bearer',
        'customer_engagement.http.bearer_token' => 'wrong-bearer',
        'customer_engagement.http.retry' => ['times' => 1, 'sleep_ms' => 0],
    ]);

    Http::fake([
        'https://sms.example.test/send' => Http::response(['ok' => true], 200),
    ]);

    (new HttpCustomerEngagementNotifier)->send('logistics', 'shipment.dispatched', ['id' => 1]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://sms.example.test/send'
            && $request->hasHeader('Authorization', 'Bearer sms-only-bearer');
    });
});

test('whatsapp endpoint uses dedicated bearer when configured', function () {
    config([
        'customer_engagement.whatsapp.enabled' => true,
        'customer_engagement.whatsapp.endpoint' => 'https://wa.example.test/messages',
        'customer_engagement.whatsapp.bearer_token' => 'wa-bearer',
        'customer_engagement.http.retry' => ['times' => 1, 'sleep_ms' => 0],
    ]);

    Http::fake([
        'https://wa.example.test/messages' => Http::response(['ok' => true], 200),
    ]);

    (new HttpCustomerEngagementNotifier)->send('logistics', 'shipment.dispatched', ['id' => 1]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://wa.example.test/messages'
            && $request->hasHeader('Authorization', 'Bearer wa-bearer');
    });
});
