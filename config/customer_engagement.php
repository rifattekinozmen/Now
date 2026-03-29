<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bildirim çözümleyici: auto | http | log | null | composite
    |--------------------------------------------------------------------------
    |
    | auto: uç nokta / log bayraklarına göre mevcut mantık (varsayılan).
    | http: yalnızca HttpCustomerEngagementNotifier (endpoint boşsa istek gitmez).
    | log: LogCustomerEngagementNotifier.
    | null: NullCustomerEngagementNotifier (tam no-op).
    | composite: Log + Http birlikte (CompositeCustomerEngagementNotifier).
    |
    */

    'driver' => env('CUSTOMER_ENGAGEMENT_DRIVER', 'auto'),

    'enabled' => (bool) env('CUSTOMER_ENGAGEMENT_LOG', false),

    'log_channel' => env('CUSTOMER_ENGAGEMENT_LOG_CHANNEL', 'single'),

    'sms' => [
        'enabled' => (bool) env('SMS_NOTIFICATIONS_ENABLED', false),
        'endpoint' => env('CUSTOMER_ENGAGEMENT_SMS_ENDPOINT'),
        /*
         * Genel webhook Bearer'ından ayrı SMS uç Bearer'ı (boşsa http.bearer_token kullanılır).
         */
        'bearer_token' => env('CUSTOMER_ENGAGEMENT_SMS_BEARER'),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),
        'endpoint' => env('CUSTOMER_ENGAGEMENT_WHATSAPP_ENDPOINT'),
        'bearer_token' => env('CUSTOMER_ENGAGEMENT_WHATSAPP_BEARER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP webhook (Twilio / Meta Cloud API / özel köprü)
    |--------------------------------------------------------------------------
    */

    'http' => [
        'endpoint' => env('CUSTOMER_ENGAGEMENT_HTTP_ENDPOINT'),
        'timeout_seconds' => (int) env('CUSTOMER_ENGAGEMENT_HTTP_TIMEOUT', 10),
        'bearer_token' => env('CUSTOMER_ENGAGEMENT_HTTP_BEARER'),
        /*
         * Doluysa gövde JSON'unun HMAC-SHA256 özeti X-Webhook-Signature: sha256=<hex> başlığında gönderilir.
         */
        'signature_secret' => env('CUSTOMER_ENGAGEMENT_HTTP_SIGNATURE_SECRET'),
        'retry' => [
            'times' => max(1, (int) env('CUSTOMER_ENGAGEMENT_HTTP_RETRY_TIMES', 2)),
            'sleep_ms' => max(0, (int) env('CUSTOMER_ENGAGEMENT_HTTP_RETRY_SLEEP_MS', 100)),
        ],
    ],

];
