<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operational log channel
    |--------------------------------------------------------------------------
    |
    | LogOperationalNotifier bu kanala yazar. logging.php içindeki bir kanal adı
    | veya varsayılan "stack"/"single" kullanılabilir.
    |
    */

    'log_channel' => env('OPERATIONS_LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Slack Incoming Webhook (opsiyonel)
    |--------------------------------------------------------------------------
    |
    | Boş bırakılırsa SlackOperationalNotifier hiç HTTP yapmaz.
    |
    */

    'slack_webhook_url' => env('OPERATIONS_SLACK_WEBHOOK_URL', ''),

    'slack_timeout_seconds' => (int) env('OPERATIONS_SLACK_TIMEOUT', 5),

];
