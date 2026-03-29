<?php

return [

    'enabled' => (bool) env('CUSTOMER_ENGAGEMENT_LOG', false),

    'log_channel' => env('CUSTOMER_ENGAGEMENT_LOG_CHANNEL', 'single'),

    'sms' => [
        'enabled' => (bool) env('SMS_NOTIFICATIONS_ENABLED', false),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),
    ],

];
