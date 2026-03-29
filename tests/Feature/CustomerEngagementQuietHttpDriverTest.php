<?php

use App\Contracts\CustomerEngagementNotifier;
use Illuminate\Support\Facades\Http;

test('http driver sends no outbound request when all endpoints are empty', function () {
    Http::fake();

    config([
        'customer_engagement.driver' => 'http',
        'customer_engagement.http.endpoint' => null,
        'customer_engagement.sms.enabled' => false,
        'customer_engagement.whatsapp.enabled' => false,
    ]);

    app(CustomerEngagementNotifier::class)->send('logistics', 'shipment.dispatched', ['shipment_id' => 1]);

    Http::assertNothingSent();
});
