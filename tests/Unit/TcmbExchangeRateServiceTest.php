<?php

use App\Services\Logistics\TcmbExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('parseRatesXml extracts ForexBuying by CurrencyCode', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Tarih_Date>
  <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD">
    <ForexBuying>34,5678</ForexBuying>
  </Currency>
  <Currency CrossOrder="1" Kod="EUR" CurrencyCode="EUR">
    <ForexBuying>36,1234</ForexBuying>
  </Currency>
</Tarih_Date>
XML;

    $svc = new TcmbExchangeRateService;
    $rates = $svc->parseRatesXml($xml);

    expect($rates)->toHaveKey('USD')
        ->and($rates['USD'])->toBe('34.5678')
        ->and($rates['EUR'])->toBe('36.1234');
});

test('tryRefreshFromRemote stores rates on successful http response', function () {
    Cache::flush();

    Http::fake([
        TcmbExchangeRateService::TCMB_TODAY_XML_URL => Http::response(
            '<?xml version="1.0"?><Tarih_Date><Currency CurrencyCode="USD"><ForexBuying>10,5</ForexBuying></Currency></Tarih_Date>',
            200,
        ),
    ]);

    $svc = new TcmbExchangeRateService;
    expect($svc->tryRefreshFromRemote())->toBeTrue()
        ->and($svc->storedRates()['USD'] ?? null)->toBe('10.5')
        ->and($svc->storedFetchedAt())->not->toBeNull();
});
