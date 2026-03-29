<?php

use App\Services\Integrations\TotalEnergies\TotalEnergiesQuoteContractValidator;

test('contract valid when price present and no schema in response', function () {
    config(['totalenergies.schema_version' => 1]);

    $r = TotalEnergiesQuoteContractValidator::validate(['price_try_per_liter' => 10], 10.0);

    expect($r['contract_valid'])->toBeTrue()
        ->and($r['schema_match'])->toBeNull()
        ->and($r['issues'])->toBe([]);
});

test('contract invalid when response schema version mismatches config', function () {
    config(['totalenergies.schema_version' => 1]);

    $r = TotalEnergiesQuoteContractValidator::validate(['schema_version' => 2, 'price_try_per_liter' => 10], 10.0);

    expect($r['contract_valid'])->toBeFalse()
        ->and($r['schema_match'])->toBeFalse()
        ->and($r['issues'])->toContain('schema_version_mismatch');
});

test('contract valid when schema version matches config and price present', function () {
    config(['totalenergies.schema_version' => 1]);

    $r = TotalEnergiesQuoteContractValidator::validate(['schema_version' => 1, 'price_try_per_liter' => 42.5], 42.5);

    expect($r['contract_valid'])->toBeTrue()
        ->and($r['schema_match'])->toBeTrue()
        ->and($r['issues'])->toBe([]);
});
