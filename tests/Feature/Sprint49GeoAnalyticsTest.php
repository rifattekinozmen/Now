<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\TaxOffice;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access geo analytics page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.analytics.geo'))
        ->assertOk()
        ->assertSee(__('Geo Analytics'));
});

it('geo analytics page requires authentication', function (): void {
    $this->get(route('admin.analytics.geo'))
        ->assertRedirect();
});

it('geo analytics aggregates orders by customer city via tax office', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $taxOffice = TaxOffice::factory()->create(['city' => 'Ankara']);
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'tax_office_id' => $taxOffice->id,
    ]);

    Order::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'freight_amount' => 1000,
        'ordered_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('admin.analytics.geo'))
        ->assertOk()
        ->assertSee('Ankara');
});
