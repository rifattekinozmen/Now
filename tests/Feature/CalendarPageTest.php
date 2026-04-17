<?php

use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('admin can access calendar index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.calendar.index'))
        ->assertSuccessful();
});

it('viewer can access calendar index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.calendar.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from calendar', function (): void {
    $this->get(route('admin.calendar.index'))
        ->assertRedirect();
});

it('calendar does not show other tenant maintenance events', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenantA->id,
        'scheduled_date' => now()->format('Y-m-d'),
    ]);
    $maintB = MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenantB->id,
        'scheduled_date' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($userA);

    $visible = MaintenanceSchedule::query()->get();
    expect($visible->pluck('id'))->not->toContain($maintB->id);
});
