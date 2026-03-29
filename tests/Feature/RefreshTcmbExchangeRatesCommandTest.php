<?php

use App\Services\Logistics\TcmbExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('logistics refresh tcmb command caches rates on success', function () {
    Cache::flush();

    Http::fake([
        TcmbExchangeRateService::TCMB_TODAY_XML_URL => Http::response(
            '<?xml version="1.0"?><Tarih_Date><Currency CurrencyCode="USD"><ForexBuying>12,34</ForexBuying></Currency></Tarih_Date>',
            200,
        ),
    ]);

    $this->artisan('logistics:refresh-tcmb-rates')
        ->assertSuccessful();

    $svc = new TcmbExchangeRateService;
    expect($svc->storedRates()['USD'] ?? null)->toBe('12.34');
});
