<?php

use App\Enums\AccountType;
use App\Models\CurrentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CurrentAccountPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.current-accounts.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s current accounts', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $accountA = CurrentAccount::factory()->create(['tenant_id' => $tenantA->id]);
    $accountB = CurrentAccount::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $accounts = CurrentAccount::query()->get();
    expect($accounts->pluck('id'))->not->toContain($accountB->id)
        ->and($accounts->pluck('id'))->toContain($accountA->id);
})->group('isolation');

// ─────────────────────────────────────────────
// ROUTE ACCESS
// ─────────────────────────────────────────────

it('admin can access the current accounts page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.finance.current-accounts.index'))
        ->assertSuccessful();
})->group('route');

it('unauthenticated user is redirected from current accounts page', function (): void {
    $this->get(route('admin.finance.current-accounts.index'))
        ->assertRedirect();
})->group('route');

// ─────────────────────────────────────────────
// POLICY
// ─────────────────────────────────────────────

it('user with write permission can create a current account via policy', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.current-accounts.write');
    $user = $user->fresh();

    $policy = new CurrentAccountPolicy;
    expect($policy->create($user))->toBeTrue();
})->group('policy');

it('user without write permission cannot create a current account', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user = $user->fresh();

    $policy = new CurrentAccountPolicy;
    expect($policy->create($user))->toBeFalse();
})->group('policy');

it('only logistics admin can delete a current account', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $writer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $writer->givePermissionTo('logistics.current-accounts.write');

    $account = CurrentAccount::factory()->create(['tenant_id' => $tenant->id]);

    $policy = new CurrentAccountPolicy;
    expect($policy->delete($admin, $account))->toBeTrue()
        ->and($policy->delete($writer->fresh(), $account))->toBeFalse();
})->group('policy');

// ─────────────────────────────────────────────
// SCOPES
// ─────────────────────────────────────────────

it('active scope returns only active accounts', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    CurrentAccount::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    CurrentAccount::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

    $active = CurrentAccount::query()->active()->get();
    expect($active)->toHaveCount(1)
        ->and($active->first()->is_active)->toBeTrue();
})->group('scope');

it('ofType scope filters by account type', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    CurrentAccount::factory()->create(['tenant_id' => $tenant->id, 'account_type' => AccountType::Customer->value]);
    CurrentAccount::factory()->forSupplier()->create(['tenant_id' => $tenant->id]);

    $customers = CurrentAccount::query()->ofType(AccountType::Customer)->get();
    expect($customers)->toHaveCount(1)
        ->and($customers->first()->account_type)->toBe(AccountType::Customer);
})->group('scope');
