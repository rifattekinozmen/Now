<?php

return [

    /*
    |--------------------------------------------------------------------------
    | iPOD (teslimat kanıtı) — teslim tamamlanırken minimum alanlar
    |--------------------------------------------------------------------------
    |
    | strict: true iken imza + GPS koordinatı + teslim fotoğrafı yolu zorunlu
    | (pod_payload veya istek gövdesi üzerinden).
    |
    */

    'ipod' => [
        'strict' => (bool) env('LOGISTICS_IPOD_STRICT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Şoför yorgunluk — son X saatte maksimum sürüş süresi (saat)
    |--------------------------------------------------------------------------
    */

    'driver_fatigue' => [
        'max_driving_hours_in_window' => (float) env('LOGISTICS_MAX_DRIVING_HOURS', 9.0),
        /** Son N saat içindeki gönderilmiş sevkiyat sürüş süreleri toplanır */
        'lookback_hours' => (float) env('LOGISTICS_FATIGUE_LOOKBACK_HOURS', 24.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Banka ekstresi — taranmış PDF OCR adaptörü
    |--------------------------------------------------------------------------
    |
    | Null (varsayılan): OCR desteği yok; yalnızca metin katmanı ve CSV.
    | Gerçek OCR adaptörü için tam sınıf adı verin: ör. App\Services\Finance\TesseractOcrAdapter
    | Sınıf App\Contracts\Finance\ScannedPdfOcrAdapter arayüzünü uygulamalıdır.
    |
    */

    'bank_statement' => [
        'scanned_pdf_ocr_adapter' => env('BANK_STATEMENT_OCR_ADAPTER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | U-ETDS — Ulusal Elektronik Tebligat Dağıtım Sistemi
    |--------------------------------------------------------------------------
    |
    | Türkiye karayolu taşıma mevzuatı sefer bildirimi.
    | enabled: false iken bildirim gönderilmez.
    |
    */

    'uetds' => [
        'enabled' => (bool) env('UETDS_ENABLED', false),
        'api_url' => env('UETDS_API_URL', 'https://uetds.gov.tr/api/v1'),
        'api_key' => env('UETDS_API_KEY'),
    ],

];
