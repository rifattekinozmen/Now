<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TotalEnergies API (iskele)
    |--------------------------------------------------------------------------
    |
    | Gerçek uç nokta ve sözleşme netleşince `TotalEnergiesFuelQuoteService` içinde
    | HTTP çağrısı eklenebilir. Şimdilik kapalı / stub davranış.
    |
    */

    'enabled' => (bool) env('TOTALENERGIES_ENABLED', false),

    'api_key' => env('TOTALENERGIES_API_KEY'),

    'base_url' => env('TOTALENERGIES_BASE_URL', 'https://api.totalenergies.example'),

    'quote_path' => env('TOTALENERGIES_QUOTE_PATH', '/diesel-quote'),

    'default_region' => env('TOTALENERGIES_REGION', 'TR'),

    'timeout_seconds' => (int) env('TOTALENERGIES_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Yanıttan fiyat çıkarma (dot notation)
    |--------------------------------------------------------------------------
    |
    | Sözleşmeye göre ilk bulunan sayısal yol kullanılır. Örn: data.items.0.price
    |
    */

    'response_price_paths' => [
        'price_eur_per_liter',
        'data.price',
    ],

];
