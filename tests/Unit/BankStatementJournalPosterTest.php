<?php

use App\Models\BankStatementCsvImport;
use App\Models\ChartAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Finance\BankStatementJournalPoster;
use App\Services\Finance\JournalPostingService;

test('bank statement poster creates balanced entry and stamps import row', function () {
    $user = User::factory()->create();
    $tenantId = (int) $user->tenant_id;
    $bank = ChartAccount::factory()->create([
        'tenant_id' => $tenantId,
        'code' => '102',
        'name' => 'Bank',
        'type' => 'asset',
    ]);
    $ar = ChartAccount::factory()->create([
        'tenant_id' => $tenantId,
        'code' => '120',
        'name' => 'AR',
        'type' => 'asset',
    ]);
    $customer = Customer::factory()->create([
        'tenant_id' => $tenantId,
        'legal_name' => 'Test Customer A.Ş.',
    ]);

    $import = BankStatementCsvImport::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'rows' => [
            [
                'booked_at' => '2026-03-20',
                'amount' => '99.50',
                'description' => 'Havale',
            ],
        ],
    ]);

    $poster = new BankStatementJournalPoster(new JournalPostingService);
    $entry = $poster->postMatchedRow(
        $import,
        0,
        $bank->id,
        $ar->id,
        $customer->id,
        $user->id,
    );

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->lines)->toHaveCount(2)
        ->and((string) $import->fresh()->rows[0]['journal_entry_id'])->toBe((string) $entry->id);
});

test('bank statement poster returns existing journal when row already posted', function () {
    $user = User::factory()->create();
    $tenantId = (int) $user->tenant_id;
    $bank = ChartAccount::factory()->create(['tenant_id' => $tenantId, 'code' => '102', 'type' => 'asset']);
    $ar = ChartAccount::factory()->create(['tenant_id' => $tenantId, 'code' => '120', 'type' => 'asset']);
    $customer = Customer::factory()->create(['tenant_id' => $tenantId]);

    $import = BankStatementCsvImport::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'rows' => [
            [
                'booked_at' => '2026-03-20',
                'amount' => '10.00',
                'description' => 'X',
                'journal_entry_id' => null,
            ],
        ],
    ]);

    $poster = new BankStatementJournalPoster(new JournalPostingService);
    $first = $poster->postMatchedRow($import, 0, $bank->id, $ar->id, $customer->id, $user->id);
    $second = $poster->postMatchedRow($import->fresh(), 0, $bank->id, $ar->id, $customer->id, $user->id);

    expect($second->id)->toBe($first->id)
        ->and(JournalEntry::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count())->toBe(1);
});
