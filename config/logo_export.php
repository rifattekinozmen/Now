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
        'TenantId' => 'tenant_id',
        'OrderedAt' => 'ordered_at',
        'OrderStatus' => 'status',
        'LoadingSite' => 'loading_site',
        'UnloadingSite' => 'unloading_site',
        'Incoterms' => 'incoterms',
        'DistanceKm' => 'distance_km',
        'Tonnage' => 'tonnage',
        'ExchangeRate' => 'exchange_rate',
        'DeliveryOrderNo' => 'meta.delivery_order_no',
        'OrderNotes' => 'meta.notes',
        'InternalReference' => 'meta.internal_reference',
        'MaterialCode' => 'meta.material_code',
        'PlantCode' => 'meta.plant_code',
        'StorageLocation' => 'meta.storage_location',
        'GrossWeightKg' => 'gross_weight_kg',
        'TaraWeightKg' => 'tara_weight_kg',
        'NetWeightKg' => 'net_weight_kg',
        'MoisturePercent' => 'moisture_percent',
    ],

    /*
    | CustomerPartnerNo, CustomerTaxId, CustomerTradeName, CustomerPaymentTermDays
    | LogoErpExportService içinde müşteri alanlarından üretilir (partner_number, tax_id,
    | trade_name, payment_term_days).
    | meta.* yolları `orders.meta` JSON alanından (dot notation) okunur.
    */

];
