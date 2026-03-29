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
    | GET sorgu parametreleri (sözleşmeye göre genişletin)
    |--------------------------------------------------------------------------
    |
    | `region` her zaman `default_region` ile birleştirilir; aynı anahtar burada
    | verilirse bu dizi önceliklidir.
    |
    */

    'quote_query' => [
        // 'product' => 'diesel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Yanıttan fiyat çıkarma (dot notation)
    |--------------------------------------------------------------------------
    |
    | Sözleşmeye göre ilk bulunan sayısal yol kullanılır. Örn: data.items.0.price,
    | result.fuel_price_try, quotes.0.amount
    |
    */

    'response_price_paths' => [
        'price_eur_per_liter',
        'price_try_per_liter',
        'price',
        'data.price',
        'data.unit_price',
        'result.price',
        'result.fuel_price',
        'result.fuel_price_try',
        'data.fuel.diesel',
        'data.items.0.price',
        'quotes.0.amount',
        'quotes.0.unit_price',
        'data.0.price',
    ],

    /*
    |--------------------------------------------------------------------------
    | Yanıttan para birimi (isteğe bağlı, dot notation)
    |--------------------------------------------------------------------------
    |
    | İlk bulunan string değer kullanılır. Bulunamazsa default_currency uygulanır.
    |
    */

    'response_currency_paths' => [
        'currency',
        'data.currency',
        'result.currency',
        'meta.currency',
    ],

    'default_currency' => env('TOTALENERGIES_DEFAULT_CURRENCY', 'EUR'),

];
