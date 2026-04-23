<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesArchivePriceImportService;
use Illuminate\Support\Facades\Http;

test('archive import can parse public api without credentials in dry run', function () {
    Http::fake([
        'https://api.example/exapi/fuel_price_cities' => Http::response([
            ['city_id' => 1, 'city_name' => 'ADANA', 'city_excel_name' => 'ADANA'],
        ], 200),
        'https://api.example/exapi/fuel_price_counties/1*' => Http::response([
            ['county_id' => 340, 'county_name' => 'MERKEZ', 'county_excel_name' => 'MERKEZ'],
        ], 200),
        'https://api.example/exapi/fuel_prices_by_date' => Http::response([
            ['pricedate' => '2026-04-01', 'motorin' => 79.38, 'kursunsuz_95_excellium_95' => 64.36, 'otogaz' => 0],
        ], 200),
    ]);

    $service = new TotalEnergiesArchivePriceImportService(
        enabled: true,
        loginUrl: 'https://akaryakit.example/login',
        archiveUrl: 'https://akaryakit.example/archive',
        archiveApiBaseUrl: 'https://api.example',
        username: '',
        password: '',
        usernameField: 'Username',
        passwordField: 'Password',
        csrfField: '__RequestVerificationToken',
        defaultCurrency: 'TRY',
        tableColumnMap: [
            'diesel' => ['Motorin'],
            'gasoline' => ['Kurşunsuz 95'],
        ],
        baseArchiveQuery: [],
    );

    $result = $service->importArchivePrices(tenantId: 1, province: 'Adana', district: 'Merkez', dryRun: true);

    expect($result['ok'])->toBeTrue()
        ->and($result['rows'])->toBe(1)
        ->and($result['imported'])->toBe(3);
});

test('archive import returns guidance when page has no parseable table', function () {
    Http::fake([
        'https://api.example/exapi/fuel_price_cities' => Http::response([], 200),
        'https://akaryakit.example/archive*' => Http::response('<html><body>no table</body></html>', 200),
    ]);

    $service = new TotalEnergiesArchivePriceImportService(
        enabled: true,
        loginUrl: 'https://akaryakit.example/login',
        archiveUrl: 'https://akaryakit.example/archive',
        archiveApiBaseUrl: 'https://api.example',
        username: '',
        password: '',
        usernameField: 'Username',
        passwordField: 'Password',
        csrfField: '__RequestVerificationToken',
        defaultCurrency: 'TRY',
        tableColumnMap: [
            'diesel' => ['Motorin'],
        ],
        baseArchiveQuery: [],
    );

    $result = $service->importArchivePrices(tenantId: 1, province: 'Adana', district: 'Merkez', dryRun: true);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('Fiyat arşivi tablosundan satır okunamadı');
});
