<?php

use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('order clerk can create order via livewire', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsOrderClerk()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user);

    Livewire::test('pages::admin.orders-index')
        ->set('customer_id', (string) $customer->id)
        ->set('currency_code', 'TRY')
        ->call('saveOrder')
        ->assertHasNoErrors();
});

test('order clerk cannot create customer via livewire', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsOrderClerk()->create();

    $this->actingAs($user);

    Livewire::test('pages::admin.customers-index')
        ->set('legal_name', 'Blocked AS')
        ->set('tax_id', '')
        ->set('trade_name', '')
        ->call('saveCustomer')
        ->assertForbidden();
});
