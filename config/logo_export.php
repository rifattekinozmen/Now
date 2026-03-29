<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logo Connect XML — ek Order alt alanları
    |--------------------------------------------------------------------------
    |
    | Anahtar: XML etiketi. Değer: Order model özelliği veya özel anahtar.
    | Özel: ordered_at → ISO-8601, status → enum değeri.
    |
    */

    'order_fields' => [
        'OrderedAt' => 'ordered_at',
        'OrderStatus' => 'status',
        'LoadingSite' => 'loading_site',
        'UnloadingSite' => 'unloading_site',
        'Incoterms' => 'incoterms',
        'DistanceKm' => 'distance_km',
        'Tonnage' => 'tonnage',
        'ExchangeRate' => 'exchange_rate',
    ],

    /*
    | CustomerPartnerNo / CustomerTaxId XML etiketleri LogoErpExportService içinde
    | müşteri partner_number ve tax_id alanlarından üretilir.
    */

];
