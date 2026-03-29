<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

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

test('customer show locations tab lists distinct unloading sites from orders', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
        'unloading_site' => 'İskenderun Liman B',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.customer-show', ['customer' => $customer])
        ->set('activeTab', 'locations')
        ->assertSee('İskenderun Liman B', escape: false);
});
