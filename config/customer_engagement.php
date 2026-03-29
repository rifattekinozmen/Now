<?php

return [

    'enabled' => (bool) env('CUSTOMER_ENGAGEMENT_LOG', false),

    'log_channel' => env('CUSTOMER_ENGAGEMENT_LOG_CHANNEL', 'single'),

    'sms' => [
        'enabled' => (bool) env('SMS_NOTIFICATIONS_ENABLED', false),
        'endpoint' => env('CUSTOMER_ENGAGEMENT_SMS_ENDPOINT'),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),
        'endpoint' => env('CUSTOMER_ENGAGEMENT_WHATSAPP_ENDPOINT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP webhook (Twilio / Meta Cloud API / özel köprü)
    |--------------------------------------------------------------------------
    */

    'http' => [
        'endpoint' => env('CUSTOMER_ENGAGEMENT_HTTP_ENDPOINT'),
        'timeout_seconds' => (int) env('CUSTOMER_ENGAGEMENT_HTTP_TIMEOUT', 10),
    ],

];
