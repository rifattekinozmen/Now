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

];
