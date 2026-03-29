<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use Illuminate\Support\Facades\Http;

test('totalenergies stub returns not ok when disabled', function () {
    $svc = new TotalEnergiesFuelQuoteService(false, null, 'https://example.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['price_eur_per_liter'])->toBeNull();
});

test('totalenergies stub returns not ok when enabled but api key empty', function () {
    $svc = new TotalEnergiesFuelQuoteService(true, '', 'https://example.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse();
});

test('totalenergies returns ok when http returns price', function () {
    Http::fake([
        'https://api.test/diesel-quote*' => Http::response(['price_eur_per_liter' => 1.55], 200),
    ]);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(1.55);
});

test('totalenergies rejects placeholder base url', function () {
    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.totalenergies.example', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['price_eur_per_liter'])->toBeNull();
});
