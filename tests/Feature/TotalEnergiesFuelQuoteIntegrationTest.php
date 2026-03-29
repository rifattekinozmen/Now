<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('fromConfig sends api key accept and region query to quote endpoint', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'test-api-key',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/api/v1/diesel',
        'totalenergies.default_region' => 'TR',
        'totalenergies.quote_query' => ['product' => 'diesel'],
        'totalenergies.timeout_seconds' => 10,
    ]);

    Http::fake([
        'https://fuel.example.com/api/v1/diesel*' => Http::response([
            'price_try_per_liter' => 52.4,
            'currency' => 'TRY',
        ], 200),
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(52.4)
        ->and($result['schema_version'])->toBe(1);

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && str_contains($request->url(), 'region=TR')
            && str_contains($request->url(), 'product=diesel');
    });
});

test('post quote sends merged json body with api key headers', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'post-api-key',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/api/v2/quote',
        'totalenergies.quote_http_method' => 'post',
        'totalenergies.default_region' => 'TR',
        'totalenergies.quote_query' => ['product' => 'diesel'],
        'totalenergies.quote_json_body' => ['fuel_grade' => 'motorin'],
        'totalenergies.timeout_seconds' => 10,
    ]);

    Http::fake([
        'https://fuel.example.com/api/v2/quote' => Http::response([
            'price_try_per_liter' => 48.1,
            'currency' => 'TRY',
        ], 200),
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(48.1);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://fuel.example.com/api/v2/quote') {
            return false;
        }
        $data = $request->data();
        if (! is_array($data)) {
            return false;
        }

        return $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-API-Key', 'post-api-key')
            && ($data['region'] ?? null) === 'TR'
            && ($data['product'] ?? null) === 'diesel'
            && ($data['fuel_grade'] ?? null) === 'motorin';
    });
});

test('fetch parses fixture shaped nested json body', function () {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/totalenergies_quote_schema_v1.json'), true);
    expect($fixture)->toBeArray();

    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'fixture-key',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/v1/quote',
        'totalenergies.default_region' => 'TR',
        'totalenergies.timeout_seconds' => 10,
        'totalenergies.response_price_paths' => [
            'data.fuel.diesel_try',
            'price_try_per_liter',
        ],
        'totalenergies.response_currency_paths' => [
            'data.currency',
            'currency',
        ],
        'totalenergies.response_location_paths' => [
            'location.province',
            'data.province',
        ],
    ]);

    Http::fake([
        'https://fuel.example.com/v1/quote*' => Http::response($fixture, 200),
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue()
        ->and($result['price_eur_per_liter'])->toBe(49.85)
        ->and($result['currency'])->toBe('TRY')
        ->and($result['location_label'])->toBe('Adana')
        ->and($result['schema_version'])->toBe(1);
});

test('get quote merges province and district from config into query string', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'k',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/diesel-quote',
        'totalenergies.default_region' => 'TR',
        'totalenergies.province' => 'Adana',
        'totalenergies.district' => 'Seyhan',
        'totalenergies.timeout_seconds' => 10,
    ]);

    Http::fake([
        'https://fuel.example.com/diesel-quote*' => Http::response(['price_try_per_liter' => 50], 200),
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'province=Adana')
            && str_contains($request->url(), 'district=Seyhan');
    });
});

test('placeholder base url skips http and returns not ok', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'k',
        'totalenergies.base_url' => 'https://api.totalenergies.example',
        'totalenergies.quote_path' => '/diesel-quote',
        'totalenergies.timeout_seconds' => 10,
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeFalse()
        ->and($result['price_eur_per_liter'])->toBeNull();

    Http::assertNothingSent();
});

test('get quote merges extra headers from config', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'k',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/q',
        'totalenergies.timeout_seconds' => 10,
        'totalenergies.extra_headers' => [
            'X-Contract-Id' => 'acme-2026',
            'Authorization' => 'Bearer secondary-token',
        ],
        'totalenergies.retry' => ['times' => 1, 'sleep_ms' => 0],
    ]);

    Http::fake([
        'https://fuel.example.com/q*' => Http::response(['price_try_per_liter' => 40], 200),
    ]);

    $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

    expect($result['ok'])->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('X-Contract-Id', 'acme-2026')
            && $request->hasHeader('Authorization', 'Bearer secondary-token')
            && $request->hasHeader('X-API-Key', 'k');
    });
});
