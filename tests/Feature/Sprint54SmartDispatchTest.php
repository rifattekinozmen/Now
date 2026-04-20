<?php

use App\Models\Employee;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Logistics\SmartDispatchService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('smart dispatch service returns suggestions for available vehicles', function (): void {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id, 'tonnage' => 10]);
    Vehicle::factory()->count(3)->create(['tenant_id' => $tenant->id]);
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    $service = new SmartDispatchService;
    $suggestions = $service->suggest($order, 3);

    expect($suggestions)->toHaveCount(3);
    expect($suggestions->first())->toHaveKeys(['vehicle_id', 'driver_id', 'plate', 'driver_name', 'score', 'reasons']);
    expect($suggestions->first()['score'])->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
});

it('smart dispatch service returns empty collection when no vehicles', function (): void {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new SmartDispatchService;
    $suggestions = $service->suggest($order, 3);

    expect($suggestions)->toBeEmpty();
});

it('smart dispatch suggestions are sorted by score descending', function (): void {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    Vehicle::factory()->count(5)->create(['tenant_id' => $tenant->id]);

    $service = new SmartDispatchService;
    $suggestions = $service->suggest($order, 5);

    $scores = $suggestions->pluck('score')->all();
    $sorted = collect($scores)->sortDesc()->values()->all();

    expect($scores)->toBe($sorted);
});

it('smart dispatch respects top-n limit', function (): void {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    Vehicle::factory()->count(10)->create(['tenant_id' => $tenant->id]);

    $service = new SmartDispatchService;
    expect($service->suggest($order, 3))->toHaveCount(3);
    expect($service->suggest($order, 1))->toHaveCount(1);
});
