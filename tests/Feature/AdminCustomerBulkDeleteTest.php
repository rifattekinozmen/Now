<?php

use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

test('user can bulk delete customers in their tenant', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $c1 = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $c2 = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user);

    Livewire::test('pages::admin.customers-index')
        ->set('selectedIds', [(string) $c1->id, (string) $c2->id])
        ->call('bulkDeleteSelected')
        ->assertHasNoErrors();

    expect(Customer::query()->whereIn('id', [$c1->id, $c2->id])->count())->toBe(0);
});

test('toggle select page selects all customers on the current page as integer ids', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $c1 = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $c2 = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    $component = Livewire::actingAs($user)
        ->test('pages::admin.customers-index')
        ->call('toggleSelectPage');

    $ids = $component->get('selectedIds');
    expect($ids)->toHaveCount(2);
    expect($ids)->toContain($c1->id, $c2->id);
    expect($ids)->toBe(array_map('intval', $ids));

    $component->call('toggleSelectPage');
    expect($component->get('selectedIds'))->toBe([]);
});

test('user can update customer from index livewire form', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'legal_name' => 'Old Legal',
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.customers-index')
        ->call('startEditCustomer', $customer->id)
        ->set('legal_name', 'New Legal Name')
        ->set('payment_term_days', 45)
        ->call('updateCustomer')
        ->assertHasNoErrors();

    expect($customer->fresh()->legal_name)->toBe('New Legal Name');
    expect($customer->fresh()->payment_term_days)->toBe(45);
});

test('user can delete single customer from index', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    Livewire::actingAs($user)
        ->test('pages::admin.customers-index')
        ->call('deleteCustomer', $customer->id)
        ->assertHasNoErrors();

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeFalse();
});
