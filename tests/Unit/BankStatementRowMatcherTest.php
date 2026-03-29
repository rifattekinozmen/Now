<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Finance\BankStatementRowMatcher;

test('matcher attaches tax id candidate', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'tax_id' => '9876543210',
        'legal_name' => 'Demo Ltd',
    ]);

    $matcher = new BankStatementRowMatcher;
    $rows = $matcher->enrichRowsForTenant((int) $tenant->id, [[
        'booked_at' => '2026-01-01',
        'amount' => '10.00',
        'description' => 'Transfer ref 9876543210',
    ]]);

    expect($rows[0]['match_candidates'] ?? [])->toHaveCount(1)
        ->and($rows[0]['match_candidates'][0]['customer_id'])->toBe((int) $customer->id)
        ->and($rows[0]['match_candidates'][0]['reason'])->toBe('tax_id');
});

test('matcher matches iban from customer meta', function () {
    $tenant = Tenant::factory()->create();
    $iban = 'TR330006100519786457841326';
    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'tax_id' => null,
        'legal_name' => 'Iban Co',
        'meta' => ['iban' => $iban],
    ]);

    $matcher = new BankStatementRowMatcher;
    $rows = $matcher->enrichRowsForTenant((int) $tenant->id, [[
        'booked_at' => '2026-01-02',
        'amount' => '1.00',
        'description' => 'Havale '.$iban.' OK',
    ]]);

    expect($rows[0]['match_candidates'] ?? [])->not->toBeEmpty()
        ->and($rows[0]['match_candidates'][0]['reason'])->toBe('iban');
});

test('matcher matches spaced iban in description', function () {
    $tenant = Tenant::factory()->create();
    $iban = 'TR330006100519786457841326';
    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'tax_id' => null,
        'legal_name' => 'Spaced Iban Co',
        'meta' => ['iban' => $iban],
    ]);

    $matcher = new BankStatementRowMatcher;
    $spaced = 'TR33 0006 1005 1978 6457 8413 26';
    $rows = $matcher->enrichRowsForTenant((int) $tenant->id, [[
        'booked_at' => '2026-01-03',
        'amount' => '2.00',
        'description' => 'Ödeme '.$spaced.' ref',
    ]]);

    expect($rows[0]['match_candidates'] ?? [])->not->toBeEmpty()
        ->and($rows[0]['match_candidates'][0]['reason'])->toBe('iban');
});

test('matcher matches partner number substring in description', function () {
    $tenant = Tenant::factory()->create();
    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'partner_number' => 'SAP-BP-9911',
        'legal_name' => 'Partner Match Ltd',
    ]);

    $matcher = new BankStatementRowMatcher;
    $rows = $matcher->enrichRowsForTenant((int) $tenant->id, [[
        'booked_at' => '2026-01-04',
        'amount' => '3.00',
        'description' => 'Havale SAP-BP-9911 müşteri ödemesi',
    ]]);

    expect($rows[0]['match_candidates'] ?? [])->not->toBeEmpty()
        ->and($rows[0]['match_candidates'][0]['reason'])->toBe('partner_number');
});
