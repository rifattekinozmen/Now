<?php

use App\Models\ChartAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\JournalPostingService;
use App\Services\Finance\TrialBalanceService;

test('trial balance aggregates debits and credits per account for the period', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $bank = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '102',
        'type' => 'asset',
    ]);
    $ar = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '120',
        'type' => 'asset',
    ]);

    $j = new JournalPostingService;
    $j->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-15',
        'T1',
        'x',
        [
            ['chart_account_id' => $bank->id, 'debit' => '50.00', 'credit' => '0'],
            ['chart_account_id' => $ar->id, 'debit' => '0', 'credit' => '50.00'],
        ],
    );
    $j->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-20',
        'T2',
        'y',
        [
            ['chart_account_id' => $bank->id, 'debit' => '25.50', 'credit' => '0'],
            ['chart_account_id' => $ar->id, 'debit' => '0', 'credit' => '25.50'],
        ],
    );

    $svc = new TrialBalanceService;
    $rows = $svc->periodAccountTotals((int) $tenant->id, '2026-03-01', '2026-03-31');

    $byCode = collect($rows)->keyBy('code');
    expect($byCode->has('102'))->toBeTrue()
        ->and($byCode->get('102')['total_debit'])->toBe('75.50')
        ->and($byCode->get('102')['total_credit'])->toBe('0.00')
        ->and($byCode->get('120')['total_debit'])->toBe('0.00')
        ->and($byCode->get('120')['total_credit'])->toBe('75.50');

    $grand = $svc->grandTotals($rows);
    expect($grand['total_debit'])->toBe($grand['total_credit']);
});

test('trial balance excludes other tenants', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $u1 = User::factory()->create(['tenant_id' => $t1->id]);
    $a1 = ChartAccount::factory()->create(['tenant_id' => $t1->id, 'code' => '100']);
    $b1 = ChartAccount::factory()->create(['tenant_id' => $t1->id, 'code' => '200']);

    $j = new JournalPostingService;
    $j->createBalancedEntry(
        (int) $t1->id,
        (int) $u1->id,
        '2026-04-01',
        'X',
        '',
        [
            ['chart_account_id' => $a1->id, 'debit' => '10.00', 'credit' => '0'],
            ['chart_account_id' => $b1->id, 'debit' => '0', 'credit' => '10.00'],
        ],
    );

    $svc = new TrialBalanceService;
    expect($svc->periodAccountTotals((int) $t2->id, '2026-04-01', '2026-04-30'))->toBeEmpty()
        ->and($svc->periodAccountTotals((int) $t1->id, '2026-04-01', '2026-04-30'))->not->toBeEmpty();
});

test('trial balance rejects inverted date range', function () {
    $svc = new TrialBalanceService;
    $svc->periodAccountTotals(1, '2026-05-10', '2026-05-01');
})->throws(InvalidArgumentException::class);
