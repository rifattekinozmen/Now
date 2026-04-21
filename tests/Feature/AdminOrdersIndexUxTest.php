<?php

use App\Models\User;
use Livewire\Livewire;

test('orders index toggleNewOrderForm opens and closes new order panel', function () {
    /** @var User $user */
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.orders-index')
        ->call('toggleNewOrderForm')
        ->assertSet('newOrderFormOpen', true)
        ->call('toggleNewOrderForm')
        ->assertSet('newOrderFormOpen', false);
});

test('orders index clearOrderAdvancedFilters resets advanced filter fields', function () {
    /** @var User $user */
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.orders-index')
        ->set('filterStatus', 'draft')
        ->set('filterCustomer', '1')
        ->set('filterDateFrom', '2026-01-01')
        ->set('filterDateTo', '2026-01-31')
        ->call('clearOrderAdvancedFilters')
        ->assertSet('filterStatus', '')
        ->assertSet('filterCustomer', '')
        ->assertSet('filterDateFrom', '')
        ->assertSet('filterDateTo', '');
});
