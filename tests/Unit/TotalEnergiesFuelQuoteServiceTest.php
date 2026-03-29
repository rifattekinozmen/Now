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

test('totalenergies reads nested price path from config', function () {
    config(['totalenergies.response_price_paths' => ['data.fuel.diesel']]);

    Http::fake([
        'https://api.test/diesel-quote*' => Http::response(['data' => ['fuel' => ['diesel' => 2.1]]], 200),
    ]);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(2.1);
});

test('totalenergies rejects placeholder base url', function () {
    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.totalenergies.example', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['price_eur_per_liter'])->toBeNull();
});

test('totalenergies reads price from array index path', function () {
    config(['totalenergies.response_price_paths' => ['quotes.0.unit_price']]);

    Http::fake([
        'https://api.test/diesel-quote*' => Http::response(['quotes' => [['unit_price' => 42.5]]], 200),
    ]);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(42.5);
});

test('totalenergies parses european format string price', function () {
    Http::fake([
        'https://api.test/diesel-quote*' => Http::response(['price' => '45,89'], 200),
    ]);

    config(['totalenergies.response_price_paths' => ['price']]);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(45.89);
});

test('totalenergies reads currency from configured path', function () {
    Http::fake([
        'https://api.test/diesel-quote*' => Http::response([
            'data' => ['unit_price' => 1.2, 'currency' => 'try'],
        ], 200),
    ]);

    config([
        'totalenergies.response_price_paths' => ['data.unit_price'],
        'totalenergies.response_currency_paths' => ['data.currency'],
        'totalenergies.default_currency' => 'EUR',
    ]);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['currency'])->toBe('TRY');
});

test('totalenergies uses default currency when response omits it', function () {
    Http::fake([
        'https://api.test/diesel-quote*' => Http::response(['price_eur_per_liter' => 1.5], 200),
    ]);

    config(['totalenergies.default_currency' => 'TRY']);

    $svc = new TotalEnergiesFuelQuoteService(true, 'secret', 'https://api.test', '/diesel-quote');

    $result = $svc->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['currency'])->toBe('TRY');
});
