<?php

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('cannot read another tenant\'s work orders', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);
    $orderB = WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $orders = WorkOrder::query()->get();
    expect($orders->pluck('id'))->not->toContain($orderB->id);
});

it('admin can access work orders index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.work-orders.index'))
        ->assertSuccessful();
});

it('viewer can access work orders index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.work-orders.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from work orders', function (): void {
    $this->get(route('admin.work-orders.index'))
        ->assertRedirect();
});

it('work order has correct enum casts', function (): void {
    $tenant = Tenant::factory()->create();
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => WorkOrderType::Corrective->value,
        'status' => WorkOrderStatus::Pending->value,
    ]);

    expect($wo->fresh()->type)->toBe(WorkOrderType::Corrective);
    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Pending);
    expect($wo->status->isPending())->toBeTrue();
});

it('work order can be marked completed', function (): void {
    $tenant = Tenant::factory()->create();
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => WorkOrderStatus::Pending->value,
    ]);

    $wo->update([
        'status' => WorkOrderStatus::Completed->value,
        'completed_at' => now()->format('Y-m-d'),
    ]);

    expect($wo->fresh()->status->isCompleted())->toBeTrue();
    expect($wo->fresh()->completed_at)->not->toBeNull();
});

it('work order belongs to vehicle', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($wo->vehicle->id)->toBe($vehicle->id);
});
