<?php

use App\Models\ChartAccount;
use App\Models\FiscalOpeningBalance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\BalanceSheetService;
use App\Services\Finance\JournalPostingService;
use App\Services\Finance\LegalFinancialStatementsService;
use App\Services\Finance\TrialBalanceService;

test('period with fiscal openings merges asset section with journal activity', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $bank = ChartAccount::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => '102',
        'type' => 'asset',
    ]);
    FiscalOpeningBalance::query()->create([
        'tenant_id' => $tenant->id,
        'chart_account_id' => $bank->id,
        'fiscal_year' => 2026,
        'opening_debit' => '25.00',
        'opening_credit' => '0.00',
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

    $svc = new LegalFinancialStatementsService(new BalanceSheetService(new TrialBalanceService), new TrialBalanceService);
    $r = $svc->periodStructuredSummaryWithFiscalOpenings((int) $tenant->id, '2026-03-01', '2026-03-31', 2026);

    expect($r['includes_fiscal_openings'])->toBeTrue()
        ->and($r['balance_sheet']['assets']['net'])->toBe('125.00')
        ->and($r['balance_sheet']['liabilities']['net'])->toBe('-100.00');
});

test('without opening rows flag is false and matches balance sheet service', function () {
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
        'BS2',
        '',
        [
            ['chart_account_id' => $bank->id, 'debit' => '40.00', 'credit' => '0'],
            ['chart_account_id' => $ap->id, 'debit' => '0', 'credit' => '40.00'],
        ],
    );

    $bs = new BalanceSheetService(new TrialBalanceService);
    $plain = $bs->periodStructuredSummary((int) $tenant->id, '2026-03-01', '2026-03-31');

    $svc = new LegalFinancialStatementsService($bs, new TrialBalanceService);
    $r = $svc->periodStructuredSummaryWithFiscalOpenings((int) $tenant->id, '2026-03-01', '2026-03-31', 2026);

    expect($r['includes_fiscal_openings'])->toBeFalse()
        ->and($r['balance_sheet']['assets']['net'])->toBe($plain['balance_sheet']['assets']['net']);
});
