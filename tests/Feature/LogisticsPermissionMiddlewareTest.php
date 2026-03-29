<?php

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

test('users without logistics permission cannot access admin logistics routes', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user);

    $this->get(route('admin.customers.index'))->assertForbidden();
    $this->get(route('admin.customers.export.csv'))->assertForbidden();
    $this->get(route('admin.finance.index'))->assertForbidden();
    $this->get(route('admin.finance.payment-due-calendar'))->assertForbidden();
});

test('users with tenant-user role can access admin logistics routes', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.customers.index'))->assertSuccessful();
    $this->get(route('admin.finance.index'))->assertSuccessful();
});
