<?php

use App\Enums\DeliveryNumberStatus;
use Tests\TestCase;

uses(TestCase::class);
use App\Models\Customer;
use App\Models\DeliveryNumber;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('user can add and assign pin to order in same tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()
        ->state(['sas_no' => null])
        ->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.delivery-numbers-index')
        ->set('pin_code', 'PIN-1001')
        ->set('sas_no', 'SAS-99')
        ->call('addPin')
        ->assertHasNoErrors();

    $dn = DeliveryNumber::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($dn)->not->toBeNull()
        ->and($dn->status)->toBe(DeliveryNumberStatus::Available);

    Livewire::test('pages::admin.delivery-numbers-index')
        ->set('assign_pin_code', 'PIN-1001')
        ->set('assign_order_id', (string) $order->id)
        ->call('assignPinToOrder')
        ->assertHasNoErrors();

    $dn->refresh();
    expect($dn->status)->toBe(DeliveryNumberStatus::Assigned)
        ->and($dn->order_id)->toBe($order->id)
        ->and($order->refresh()->sas_no)->toBe('SAS-99');
});

test('other tenant pin is not visible for assignment', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    DeliveryNumber::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'pin_code' => 'ONLY-A',
        'status' => DeliveryNumberStatus::Available,
    ]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $orderB = Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
    ]);

    $this->actingAs($userB);

    Livewire::test('pages::admin.delivery-numbers-index')
        ->set('assign_pin_code', 'ONLY-A')
        ->set('assign_order_id', (string) $orderB->id)
        ->call('assignPinToOrder')
        ->assertHasErrors('assign_pin_code');
});
