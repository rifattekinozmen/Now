<?php

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleGpsPosition;
use App\Services\Logistics\GeofenceCheckerService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('stores a gps position via api and returns 201', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)->postJson(route('api.v1.vehicles.gps.store', $vehicle), [
        'lat' => 39.9334,
        'lng' => 32.8597,
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'recorded_at']);

    expect(VehicleGpsPosition::where('vehicle_id', $vehicle->id)->count())->toBe(1);
});

it('gps api requires authentication', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson(route('api.v1.vehicles.gps.store', $vehicle), [
        'lat' => 39.9334,
        'lng' => 32.8597,
    ])
        ->assertStatus(401);
});

it('haversine distance is accurate within 500 metres', function (): void {
    $service = new GeofenceCheckerService;

    // ~356 m apart
    $dist = $service->haversineMetres(41.0082, 28.9784, 41.0050, 28.9784);
    expect($dist)->toBeLessThan(500);

    // ~5 km apart
    $far = $service->haversineMetres(41.0082, 28.9784, 41.0500, 28.9784);
    expect($far)->toBeGreaterThan(500);
});

it('geofence checker marks arrival when vehicle is within radius', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Dispatched->value,
        'meta' => [
            'delivery_lat' => 39.9334,
            'delivery_lng' => 32.8597,
        ],
    ]);

    $service = new GeofenceCheckerService;
    // Same point → distance = 0
    $service->checkDeliveryArrival($vehicle, 39.9334, 32.8597);

    $shipment->refresh();
    expect($shipment->meta)->toHaveKey('geofence_arrived_at');
});

it('geofence checker does not mark arrival when vehicle is far away', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Dispatched->value,
        'meta' => [
            'delivery_lat' => 39.9334,
            'delivery_lng' => 32.8597,
        ],
    ]);

    $service = new GeofenceCheckerService;
    // ~10 km away
    $service->checkDeliveryArrival($vehicle, 40.0334, 32.8597);

    $shipment->refresh();
    expect($shipment->meta)->not->toHaveKey('geofence_arrived_at');
});

it('older than scope returns positions older than n days', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    VehicleGpsPosition::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'recorded_at' => now()->subDays(31),
    ]);
    VehicleGpsPosition::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'recorded_at' => now()->subDays(5),
    ]);

    $old = VehicleGpsPosition::withoutGlobalScopes()->olderThan(30)->count();
    expect($old)->toBe(1);
});
