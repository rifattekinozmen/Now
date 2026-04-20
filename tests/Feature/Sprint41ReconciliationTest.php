<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access weekly reconciliation page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.finance.weekly-reconciliation'))
        ->assertSuccessful();
})->group('routes');

it('orders with sas_no count as matched', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sas_no' => 'SAS-2024-0001',
    ]);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sas_no' => null,
    ]);

    $matched = Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('sas_no')->where('sas_no', '!=', '')->count();
    $unmatched = Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where(function ($q): void {
        $q->whereNull('sas_no')->orWhere('sas_no', '');
    })->count();

    expect($matched)->toBe(1)
        ->and($unmatched)->toBe(1);
});

it('orders with empty sas_no count as unmatched', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sas_no' => '',
    ]);

    $unmatched = Order::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where(function ($q): void {
            $q->whereNull('sas_no')->orWhere('sas_no', '');
        })
        ->count();

    expect($unmatched)->toBeGreaterThanOrEqual(1);
});

it('weekly reconciliation route is protected', function (): void {
    $this->get(route('admin.finance.weekly-reconciliation'))
        ->assertRedirect(route('login'));
})->group('routes');

it('finance weekly reconciliation page requires logistics.admin role', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.finance.weekly-reconciliation'))
        ->assertStatus(403);
})->group('routes');
