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

        return $sig === $expected;
    });
});
