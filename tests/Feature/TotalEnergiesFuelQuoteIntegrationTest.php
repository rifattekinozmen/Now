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
