<?php

use App\Livewire\GlobalSearch;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

test('global search returns customer match for tenant', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'legal_name' => 'UniqueSearchCo Ltd',
    ]);

    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('open', true)
        ->set('q', 'UniqueSearch')
        ->assertSee('UniqueSearchCo Ltd');

    expect($customer->legal_name)->toContain('UniqueSearch');
});

test('global search is closed for guest', function () {
    Livewire::test(GlobalSearch::class)
        ->call('openSearch')
        ->assertSet('open', false);
});
