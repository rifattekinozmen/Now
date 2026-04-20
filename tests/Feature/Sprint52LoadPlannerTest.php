<?php

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access load planner page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.load-planner'))
        ->assertOk()
        ->assertSee(__('Load Planner'));
});

it('load planner requires authentication', function (): void {
    $this->get(route('admin.load-planner'))
        ->assertRedirect();
});
