<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

test('authenticated user can open order detail', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful()
        ->assertSee($order->order_number, escape: false);
});

test('user cannot open other tenant order detail', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $orderA = Order::factory()->create(['customer_id' => $customerA->id]);

    $this->actingAs($userB)
        ->get(route('admin.orders.show', $orderA))
        ->assertNotFound();
});
