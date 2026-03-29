<?php

use Illuminate\Support\Facades\Http;

test('logistics:test-totalenergies command succeeds when quote returns price', function () {
    config([
        'totalenergies.enabled' => true,
        'totalenergies.api_key' => 'cmd-key',
        'totalenergies.base_url' => 'https://fuel.example.com',
        'totalenergies.quote_path' => '/quote',
        'totalenergies.timeout_seconds' => 5,
        'totalenergies.retry' => ['times' => 1, 'sleep_ms' => 0],
    ]);

    Http::fake([
        'https://fuel.example.com/quote*' => Http::response(['price_try_per_liter' => 51.2, 'currency' => 'TRY'], 200),
    ]);

    $this->artisan('logistics:test-totalenergies')
        ->assertSuccessful();

    $this->artisan('logistics:test-totalenergies', ['--json' => true])
        ->assertSuccessful();
});

test('logistics:test-totalenergies fails when integration returns not ok', function () {
    config([
        'totalenergies.enabled' => false,
    ]);

    $this->artisan('logistics:test-totalenergies')
        ->assertFailed();
});
