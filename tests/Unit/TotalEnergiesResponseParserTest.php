<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesResponseParser;

test('schema v1 parses flat price_try_per_liter and location', function () {
    config([
        'totalenergies.response_price_paths' => ['price_try_per_liter'],
        'totalenergies.response_currency_paths' => ['currency'],
        'totalenergies.response_location_paths' => ['location.province'],
    ]);

    $p = TotalEnergiesResponseParser::fromConfig();
    $out = $p->parse([
        'price_try_per_liter' => '49,85',
        'currency' => 'try',
        'location' => ['province' => 'Adana'],
    ]);

    expect($out['price'])->toBe(49.85)
        ->and($out['currency'])->toBe('TRY')
        ->and($out['location'])->toBe('Adana');
});

test('schema v1 parses nested data.fuel.diesel path', function () {
    config([
        'totalenergies.response_price_paths' => ['data.fuel.diesel'],
        'totalenergies.response_currency_paths' => [],
        'totalenergies.response_location_paths' => [],
    ]);

    $p = TotalEnergiesResponseParser::fromConfig();
    $out = $p->parse(['data' => ['fuel' => ['diesel' => 42.1]]]);

    expect($out['price'])->toBe(42.1)
        ->and($out['currency'])->toBeNull()
        ->and($out['location'])->toBeNull();
});

test('schema v1 returns null price when no path matches', function () {
    config([
        'totalenergies.response_price_paths' => ['missing'],
        'totalenergies.response_currency_paths' => [],
        'totalenergies.response_location_paths' => [],
    ]);

    $p = TotalEnergiesResponseParser::fromConfig();
    $out = $p->parse(['other' => 1]);

    expect($out['price'])->toBeNull();
});
