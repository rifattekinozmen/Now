<?php

use App\Models\ChartAccount;
use App\Models\FiscalOpeningBalance;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('fiscal opening balances page loads for logistics user', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.finance.fiscal-opening-balances.index'))
        ->assertSuccessful()
        ->assertSee(__('Fiscal opening balances'), false);
});

test('user can create fiscal opening balance via livewire', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $acct = ChartAccount::factory()->create([
        'tenant_id' => $user->tenant_id,
        'code' => '199',
        'type' => 'asset',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.fiscal-opening-balances-index')
        ->set('chart_account_id', (string) $acct->id)
        ->set('entryFiscalYear', 2026)
        ->set('opening_debit', '12.50')
        ->set('opening_credit', '0')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect(FiscalOpeningBalance::query()->where('chart_account_id', $acct->id)->count())->toBe(1);
});

test('logistics viewer cannot save fiscal opening balance', function () {
    /** @var TestCase $this */
    $user = User::factory()->logisticsViewer()->create();
    $acct = ChartAccount::factory()->create([
        'tenant_id' => $user->tenant_id,
        'type' => 'asset',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.fiscal-opening-balances-index')
        ->set('chart_account_id', (string) $acct->id)
        ->set('entryFiscalYear', 2026)
        ->set('opening_debit', '1')
        ->set('opening_credit', '0')
        ->call('saveEntry')
        ->assertForbidden();
});

test('balance sheet includes fiscal openings when enabled', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $acct = ChartAccount::factory()->create([
        'tenant_id' => $user->tenant_id,
        'code' => '102',
        'type' => 'asset',
    ]);
    FiscalOpeningBalance::query()->create([
        'tenant_id' => $user->tenant_id,
        'chart_account_id' => $acct->id,
        'fiscal_year' => 2026,
        'opening_debit' => '40.00',
        'opening_credit' => '0.00',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.finance-balance-sheet')
        ->set('dateFrom', '2026-01-01')
        ->set('dateTo', '2026-01-31')
        ->set('includeFiscalOpenings', true)
        ->set('fiscalYearForOpenings', 2026)
        ->tap(function ($component) {
            $r = $component->instance()->report;
            expect($r)->not->toBeNull()
                ->and($r['includes_fiscal_openings'] ?? false)->toBeTrue()
                ->and($r['balance_sheet']['assets']['net'])->toBe('40.00');
        })
        ->assertSee(__('Fiscal opening balances applied'), false);
});
