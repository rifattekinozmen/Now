<?php

use App\Models\ChartAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\BalanceSheetService;
use App\Services\Finance\JournalPostingService;
use App\Services\Finance\TrialBalanceService;

test('balance sheet summary groups types and excludes other tenants', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $bank = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '102',
        'type' => 'asset',
    ]);
    $ap = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '320',
        'type' => 'liability',
    ]);

    $j = new JournalPostingService;
    $j->createBalancedEntry(
        (int) $tenant->id,
        (int) $user->id,
        '2026-03-15',
        'BS1',
        '',
        [
            ['chart_account_id' => $bank->id, 'debit' => '100.00', 'credit' => '0'],
            ['chart_account_id' => $ap->id, 'debit' => '0', 'credit' => '100.00'],
        ],
    );

    $svc = new BalanceSheetService(new TrialBalanceService);
    $r = $svc->periodStructuredSummary((int) $tenant->id, '2026-03-01', '2026-03-31');

    expect($r['balance_sheet']['assets']['net'])->toBe('100.00')
        ->and($r['balance_sheet']['liabilities']['net'])->toBe('-100.00')
        ->and($r['totals']['liabilities_plus_equity_net'])->toBe(bcadd($r['balance_sheet']['liabilities']['net'], $r['balance_sheet']['equity']['net'], 2));

    $other = Tenant::factory()->create();
    $empty = $svc->periodStructuredSummary((int) $other->id, '2026-03-01', '2026-03-31');
    expect($empty['balance_sheet']['assets']['total_debit'])->toBe('0.00')
        ->and($empty['balance_sheet']['assets']['total_credit'])->toBe('0.00');
});
