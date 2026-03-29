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
