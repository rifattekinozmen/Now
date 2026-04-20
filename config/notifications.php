<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "null", "twilio"
    |
    | "null" disables outbound WhatsApp/SMS notifications (safe default).
    | "twilio" uses the Twilio API (requires the credentials below).
    |
    */

    'provider' => env('NOTIFICATION_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    */

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_WHATSAPP_FROM', '+14155238886'), // Twilio sandbox default
    ],

];
