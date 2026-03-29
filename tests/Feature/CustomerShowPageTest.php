<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;

test('user can open customer profile in their tenant', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->get(route('admin.customers.show', $customer))
        ->assertSuccessful()
        ->assertSee($customer->legal_name, escape: false);
});

test('user cannot open other tenant customer profile', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($userB)
        ->get(route('admin.customers.show', $customerA))
        ->assertNotFound();
});
