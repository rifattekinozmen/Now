<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;

test('authenticated user only sees own tenant customers on admin customers page', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Customer::factory()->create([
        'tenant_id' => $tenantA->id,
        'legal_name' => 'SecretTenantACustomer',
    ]);

    $this->actingAs($userB)
        ->get(route('admin.customers.index'))
        ->assertSuccessful()
        ->assertDontSee('SecretTenantACustomer');

    $this->actingAs($userA)
        ->get(route('admin.customers.index'))
        ->assertSuccessful()
        ->assertSee('SecretTenantACustomer');
});

test('customer query scope hides other tenant rows', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Customer::factory()->create(['tenant_id' => $tenantA->id, 'legal_name' => 'OnlyA']);
    Customer::factory()->create(['tenant_id' => $tenantB->id, 'legal_name' => 'OnlyB']);

    $this->actingAs($userB);

    expect(Customer::query()->pluck('legal_name')->all())->toBe(['OnlyB']);
});

test('authenticated user only sees own tenant employees on admin employees page', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Employee::factory()->create([
        'tenant_id' => $tenantA->id,
        'first_name' => 'SecretA',
        'last_name' => 'Person',
    ]);

    $this->actingAs($userB)
        ->get(route('admin.employees.index'))
        ->assertSuccessful()
        ->assertDontSee('SecretA');

    $this->actingAs($userA)
        ->get(route('admin.employees.index'))
        ->assertSuccessful()
        ->assertSee('SecretA');
});
