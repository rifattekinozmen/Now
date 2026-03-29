<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesResponseParser;

test('parses nested diesel_try price and tr comma string', function () {
    $parser = new TotalEnergiesResponseParser(
        ['data.fuel.diesel_try', 'price_try_per_liter'],
        ['data.currency', 'currency'],
        ['location.province', 'data.province'],
    );

    $json = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/totalenergies_quote_schema_v1.json'), true);
    expect($json)->toBeArray();

    $out = $parser->parse($json);

    expect($out['price'])->toBe(49.85)
        ->and($out['currency'])->toBe('TRY')
        ->and($out['location'])->toBe('Adana');
});

test('normalize float accepts european comma decimals', function () {
    $parser = new TotalEnergiesResponseParser(['p'], [], []);

    $out = $parser->parse(['p' => '1.234,56']);
    expect($out['price'])->toBe(1234.56);
});

test('configured schema version follows config', function () {
    config(['totalenergies.schema_version' => 1]);
    expect(TotalEnergiesResponseParser::configuredSchemaVersion())->toBe(1);

    config(['totalenergies.schema_version' => 2]);
    expect(TotalEnergiesResponseParser::configuredSchemaVersion())->toBe(2);
});

test('fromConfig uses totalenergies response paths', function () {
    config([
        'totalenergies.response_price_paths' => ['result.fuel_price_try'],
        'totalenergies.response_currency_paths' => ['meta.currency'],
        'totalenergies.response_location_paths' => ['result.city'],
    ]);

    $parser = TotalEnergiesResponseParser::fromConfig();
    $out = $parser->parse([
        'result' => ['fuel_price_try' => 51.2, 'city' => 'İskenderun'],
        'meta' => ['currency' => 'try'],
    ]);

    expect($out['price'])->toBe(51.2)
        ->and($out['currency'])->toBe('TRY')
        ->and($out['location'])->toBe('İskenderun');
});
