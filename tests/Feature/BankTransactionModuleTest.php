<?php

use App\Enums\BankTransactionType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\BankTransactionPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.finance.write', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s bank transactions', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $accountA = BankAccount::factory()->create(['tenant_id' => $tenantA->id]);
    $accountB = BankAccount::factory()->create(['tenant_id' => $tenantB->id]);
    BankTransaction::factory()->create(['tenant_id' => $tenantA->id, 'bank_account_id' => $accountA->id]);
    $txB = BankTransaction::factory()->create(['tenant_id' => $tenantB->id, 'bank_account_id' => $accountB->id]);

    $this->actingAs($userA);

    $txs = BankTransaction::query()->get();
    expect($txs->pluck('id'))->not->toContain($txB->id);
})->group('isolation');

// ─────────────────────────────────────────────
// ROUTES
// ─────────────────────────────────────────────

it('admin can access bank transactions index page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)->get(route('admin.finance.bank-transactions.index'))->assertSuccessful();
})->group('routes');

it('viewer can access bank transactions index page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $this->actingAs($viewer)->get(route('admin.finance.bank-transactions.index'))->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from bank transactions page', function (): void {
    $this->get(route('admin.finance.bank-transactions.index'))->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// POLICY
// ─────────────────────────────────────────────

it('non-admin cannot delete a reconciled transaction', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.finance.write');
    $user = $user->fresh();

    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);
    $tx = BankTransaction::factory()->reconciled()->create([
        'tenant_id' => $tenant->id,
        'bank_account_id' => $account->id,
    ]);

    $policy = new BankTransactionPolicy;
    expect($policy->delete($user, $tx))->toBeFalse();
})->group('policy');

it('admin can reconcile a transaction', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);
    $tx = BankTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'bank_account_id' => $account->id,
    ]);

    $policy = new BankTransactionPolicy;
    expect($policy->reconcile($admin, $tx))->toBeTrue();
})->group('policy');

// ─────────────────────────────────────────────
// MODEL / FACTORY / SCOPES
// ─────────────────────────────────────────────

it('bank transaction factory creates valid records', function (): void {
    $tenant = Tenant::factory()->create();
    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);
    $tx = BankTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'bank_account_id' => $account->id,
    ]);

    expect($tx->id)->toBeInt()
        ->and((float) $tx->amount)->toBeGreaterThan(0)
        ->and($tx->transaction_type)->toBeInstanceOf(BankTransactionType::class)
        ->and($tx->is_reconciled)->toBeFalse();
});

it('credits scope filters credit transactions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $this->actingAs($user);

    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);
    $credit = BankTransaction::factory()->credit()->create(['tenant_id' => $tenant->id, 'bank_account_id' => $account->id]);
    $debit = BankTransaction::factory()->debit()->create(['tenant_id' => $tenant->id, 'bank_account_id' => $account->id]);

    $credits = BankTransaction::query()->credits()->get();
    expect($credits->pluck('id'))->toContain($credit->id)
        ->and($credits->pluck('id'))->not->toContain($debit->id);
});

it('unreconciled scope filters unreconciled transactions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $this->actingAs($user);

    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);

    $unreconciled = BankTransaction::factory()->create([
        'tenant_id' => $tenant->id, 'bank_account_id' => $account->id, 'is_reconciled' => false,
    ]);
    $reconciled = BankTransaction::factory()->reconciled()->create([
        'tenant_id' => $tenant->id, 'bank_account_id' => $account->id,
    ]);

    $result = BankTransaction::query()->unreconciled()->get();
    expect($result->pluck('id'))->toContain($unreconciled->id)
        ->and($result->pluck('id'))->not->toContain($reconciled->id);
});
