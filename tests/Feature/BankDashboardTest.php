<?php

use App\Enums\BankTransactionType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('unauthenticated user is redirected from bank dashboard', function (): void {
    $this->get(route('admin.finance.bank-dashboard'))
        ->assertRedirect();
});

it('admin can access bank dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.finance.bank-dashboard'))
        ->assertSuccessful();
});

it('viewer can access bank dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.finance.bank-dashboard'))
        ->assertSuccessful();
});

it('bank dashboard shows tenant scoped data only', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    BankAccount::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Account A TRY', 'currency_code' => 'TRY', 'is_active' => true]);
    BankAccount::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Account B TRY', 'currency_code' => 'TRY', 'is_active' => true]);

    $this->actingAs($userA)
        ->get(route('admin.finance.bank-dashboard'))
        ->assertSuccessful()
        ->assertSee('Account A TRY')
        ->assertDontSee('Account B TRY');
});

it('bank dashboard monthly summary reflects credit and debit transactions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id, 'currency_code' => 'TRY']);

    BankTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'bank_account_id' => $account->id,
        'transaction_type' => BankTransactionType::Credit->value,
        'amount' => 5000,
        'transaction_date' => now()->startOfMonth()->addDay(),
        'currency_code' => 'TRY',
    ]);

    BankTransaction::factory()->create([
        'tenant_id' => $tenant->id,
        'bank_account_id' => $account->id,
        'transaction_type' => BankTransactionType::Debit->value,
        'amount' => 2000,
        'transaction_date' => now()->startOfMonth()->addDays(2),
        'currency_code' => 'TRY',
    ]);

    $this->actingAs($user)
        ->get(route('admin.finance.bank-dashboard'))
        ->assertSuccessful()
        ->assertSee('5,000.00')
        ->assertSee('2,000.00');
});
