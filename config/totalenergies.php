<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JSON yanıt sözleşmesi (schema_version 1)
    |--------------------------------------------------------------------------
    |
    | GET `base_url` + `quote_path` → `Accept: application/json`, `X-API-Key`.
    | Sorgu: `quote_query` + `region` (= default_region veya TOTALENERGIES_REGION).
    |
    | Örnek gövde (uyumluluk için — gerçek alan adları `response_*_paths` ile seçilir):
    | { "price_try_per_liter": "49,85", "currency": "TRY", "location": { "province": "Adana" } }
    |
    | Çıktı: `fetchSampleDieselQuote()` içinde `price_eur_per_liter` anahtarı geçmiş uyumluluk
    | adına birim başına sayısal fiyatı taşır (para birimi `currency` ile birlikte okunmalıdır).
    |
    */

    'schema_version' => 1,

    'enabled' => (bool) env('TOTALENERGIES_ENABLED', false),

    'api_key' => env('TOTALENERGIES_API_KEY'),

    'base_url' => env('TOTALENERGIES_BASE_URL', 'https://api.totalenergies.example'),

    'quote_path' => env('TOTALENERGIES_QUOTE_PATH', '/diesel-quote'),

    'default_region' => env('TOTALENERGIES_REGION', 'TR'),

    'timeout_seconds' => (int) env('TOTALENERGIES_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | İstek yöntemi: get | post
    |--------------------------------------------------------------------------
    |
    | Sözleşmede teklif uç noktası GET ile sorgu parametreleri veya POST ile JSON
    | gövde istiyorsa burayı ayarlayın. POST için gövde: `quote_json_body` +
    | `quote_query` birleşimi (json_body boşsa yalnızca sorgu anahtarları kullanılır).
    |
    */

    'quote_http_method' => strtolower((string) env('TOTALENERGIES_QUOTE_METHOD', 'get')),

    /*
    |--------------------------------------------------------------------------
    | GET sorgu / POST JSON gövde parametreleri (sözleşmeye göre genişletin)
    |--------------------------------------------------------------------------
    |
    | GET: URL sorgu dizisi. POST: JSON nesnesine merge edilir.
    | `region` her zaman `default_region` ile birleştirilir; aynı anahtar burada
    | verilirse bu dizi önceliklidir.
    |
    */

    'quote_query' => [
        // 'product' => 'diesel',
    ],

    /*
    | POST isteklerinde ek JSON alanları (quote_query ile merge; bu anahtarlar baskın).
    */

    'quote_json_body' => [
        // 'fuel_type' => 'diesel',
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
        'price_try_per_liter',
        'result.fuel_price_try',
        'data.fuel.diesel_try',
        'price_eur_per_liter',
        'price',
        'data.price',
        'data.unit_price',
        'result.price',
        'result.fuel_price',
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

    /*
    |--------------------------------------------------------------------------
    | Konum / il metni (isteğe bağlı — Navlun il bazlı pompa notu)
    |--------------------------------------------------------------------------
    */

    'response_location_paths' => [
        'location.province',
        'location.city',
        'data.province',
        'data.city',
        'meta.province',
    ],

    'default_currency' => env('TOTALENERGIES_DEFAULT_CURRENCY', 'TRY'),

];
