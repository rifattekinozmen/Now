<?php

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\JournalPostingService;

test('journal posting creates balanced entry with two lines', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $cash = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '100',
        'name' => 'Kasa',
        'type' => 'asset',
    ]);
    $revenue = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '600',
        'name' => 'Gelir',
        'type' => 'revenue',
    ]);

    $svc = new JournalPostingService;
    $entry = $svc->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-29',
        'REF-1',
        'Test',
        [
            ['chart_account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0'],
            ['chart_account_id' => $revenue->id, 'debit' => '0', 'credit' => '100.00'],
        ],
    );

    expect($entry->lines)->toHaveCount(2)
        ->and($entry->reference)->toBe('REF-1');
});

test('journal posting rejects imbalanced lines', function () {
    $tenant = Tenant::factory()->create();
    $a = ChartAccount::factory()->create(['tenant_id' => $tenant->id, 'code' => 'A']);
    $b = ChartAccount::factory()->create(['tenant_id' => $tenant->id, 'code' => 'B']);

    $svc = new JournalPostingService;

    $svc->createBalancedEntry(
        (int) $tenant->id,
        null,
        '2026-03-29',
        null,
        null,
        [
            ['chart_account_id' => $a->id, 'debit' => '50', 'credit' => '0'],
            ['chart_account_id' => $b->id, 'debit' => '0', 'credit' => '40'],
        ],
    );
})->throws(InvalidArgumentException::class);

test('journal posting rejects account from other tenant', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $foreign = ChartAccount::factory()->create(['tenant_id' => $t2->id, 'code' => 'X']);
    $local = ChartAccount::factory()->create(['tenant_id' => $t1->id, 'code' => 'Y']);

    $svc = new JournalPostingService;

    $svc->createBalancedEntry(
        (int) $t1->id,
        null,
        '2026-03-29',
        null,
        null,
        [
            ['chart_account_id' => $local->id, 'debit' => '10', 'credit' => '0'],
            ['chart_account_id' => $foreign->id, 'debit' => '0', 'credit' => '10'],
        ],
    );
})->throws(InvalidArgumentException::class);

test('journal posting returns existing entry for duplicate source key', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $cash = ChartAccount::factory()->create(['tenant_id' => $tenant->id, 'code' => '100']);
    $ar = ChartAccount::factory()->create(['tenant_id' => $tenant->id, 'code' => '120']);

    $svc = new JournalPostingService;

    $first = $svc->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-29',
        null,
        null,
        [
            ['chart_account_id' => $cash->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['chart_account_id' => $ar->id, 'debit' => '0.00', 'credit' => '10.00'],
        ],
        JournalEntry::SOURCE_BANK_STATEMENT_ROW,
        '99:row:1',
    );

    $second = $svc->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-30',
        'different',
        'different memo',
        [
            ['chart_account_id' => $cash->id, 'debit' => '99.00', 'credit' => '0.00'],
            ['chart_account_id' => $ar->id, 'debit' => '0.00', 'credit' => '99.00'],
        ],
        JournalEntry::SOURCE_BANK_STATEMENT_ROW,
        '99:row:1',
    );

    expect($second->id)->toBe($first->id)
        ->and($second->lines)->toHaveCount(2);
});
