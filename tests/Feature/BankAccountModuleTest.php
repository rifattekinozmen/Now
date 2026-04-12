<?php

use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('unauthenticated user is redirected from bank accounts', function (): void {
    $this->get(route('admin.finance.bank-accounts.index'))
        ->assertRedirect();
});

it('admin can access bank accounts index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.finance.bank-accounts.index'))
        ->assertSuccessful();
});

it('viewer can access bank accounts index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.finance.bank-accounts.index'))
        ->assertSuccessful();
});

it('cannot read another tenant\'s bank accounts', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    BankAccount::factory()->create(['tenant_id' => $tenantA->id]);
    $accountB = BankAccount::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $records = BankAccount::query()->get();
    expect($records->pluck('id'))->not->toContain($accountB->id);
});

it('bank account has correct boolean cast', function (): void {
    $tenant = Tenant::factory()->create();
    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    expect($account->fresh()->is_active)->toBeTrue();
});

it('bank account inactive factory state works', function (): void {
    $tenant = Tenant::factory()->create();
    $account = BankAccount::factory()->inactive()->create(['tenant_id' => $tenant->id]);

    expect($account->fresh()->is_active)->toBeFalse();
});

it('bank account belongs to tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id]);

    expect($account->tenant->id)->toBe($tenant->id);
});

it('bank account opening balance is cast to decimal', function (): void {
    $tenant = Tenant::factory()->create();
    $account = BankAccount::factory()->create(['tenant_id' => $tenant->id, 'opening_balance' => 12345.67]);

    expect((float) $account->fresh()->opening_balance)->toBe(12345.67);
});
