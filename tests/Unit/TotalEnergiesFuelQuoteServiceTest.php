<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;

test('totalenergies stub returns not ok when disabled', function () {
    $svc = new TotalEnergiesFuelQuoteService(false, null, 'https://example.test');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['price_eur_per_liter'])->toBeNull();
});

test('totalenergies stub returns not ok when enabled but api key empty', function () {
    $svc = new TotalEnergiesFuelQuoteService(true, '', 'https://example.test');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse();
});

test('totalenergies stub returns not ok when enabled with key but http not implemented', function () {
    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://example.test');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->not->toBeEmpty();
});
