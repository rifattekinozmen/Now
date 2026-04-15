<?php

use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\CurrentAccount;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('unauthenticated user is redirected from account transactions', function (): void {
    $this->get(route('admin.finance.account-transactions.index'))
        ->assertRedirect();
});

it('viewer can access account transactions index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.finance.account-transactions.index'))
        ->assertSuccessful();
});

it('cannot read another tenant\'s account transactions', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    $txA = AccountTransaction::factory()->create(['tenant_id' => $tenantA->id]);
    $txB = AccountTransaction::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $records = AccountTransaction::query()->get();
    expect($records->pluck('id'))->toContain($txA->id)
        ->and($records->pluck('id'))->not->toContain($txB->id);
});

it('transaction type is cast to enum', function (): void {
    $tenant = Tenant::factory()->create();
    $tx = AccountTransaction::factory()->debit()->create(['tenant_id' => $tenant->id]);

    expect($tx->fresh()->transaction_type)->toBe(TransactionType::Debit);
    expect($tx->transaction_type->label())->toBe(__('Debit'));
    expect($tx->transaction_type->color())->toBe('red');
});

it('payment factory state sets correct type', function (): void {
    $tenant = Tenant::factory()->create();
    $tx = AccountTransaction::factory()->payment()->create(['tenant_id' => $tenant->id]);

    expect($tx->fresh()->transaction_type)->toBe(TransactionType::Payment);
});

it('account transaction belongs to current account', function (): void {
    $tenant = Tenant::factory()->create();
    $account = CurrentAccount::factory()->create(['tenant_id' => $tenant->id]);
    $tx = AccountTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'current_account_id' => $account->id,
    ]);

    expect($tx->currentAccount->id)->toBe($account->id);
});

it('current account has many transactions', function (): void {
    $tenant = Tenant::factory()->create();
    $account = CurrentAccount::factory()->create(['tenant_id' => $tenant->id]);

    AccountTransaction::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'current_account_id' => $account->id,
    ]);

    expect($account->transactions)->toHaveCount(3);
});

it('overdue scope returns only past due transactions', function (): void {
    $tenant = Tenant::factory()->create();

    AccountTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'due_date' => now()->subDays(5)->format('Y-m-d'),
    ]);
    AccountTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'due_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $overdue = AccountTransaction::query()->overdue()->get();
    expect($overdue)->toHaveCount(1);
});
