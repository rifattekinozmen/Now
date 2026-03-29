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

];
