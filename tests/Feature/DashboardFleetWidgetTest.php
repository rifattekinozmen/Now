<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Logistics\FleetSummaryService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    RolesAndPermissionsSeeder::ensureDefaults();
});

test('fleet kpi returns correct vehicle count for tenant', function () {
    $user = User::factory()->create();
    Vehicle::factory()->count(3)->create(['tenant_id' => $user->tenant_id]);

    $svc = new FleetSummaryService;
    $kpi = $svc->getFleetKpi($user->tenant_id);

    expect($kpi['total_vehicles'])->toBe(3);
});

test('fleet kpi does not bleed across tenants', function () {
    $userA = User::factory()->create();
    $tenantB = Tenant::factory()->create();
    Vehicle::factory()->count(5)->create(['tenant_id' => $tenantB->id]);

    $svc = new FleetSummaryService;
    $kpi = $svc->getFleetKpi($userA->tenant_id);

    expect($kpi['total_vehicles'])->toBe(0);
});

test('fleet kpi counts vehicles with inspection due within 30 days', function () {
    $user = User::factory()->create();

    // Due in 10 days — should be counted
    Vehicle::factory()->create([
        'tenant_id' => $user->tenant_id,
        'inspection_valid_until' => now()->addDays(10),
    ]);
    // Due in 60 days — should NOT be counted
    Vehicle::factory()->create([
        'tenant_id' => $user->tenant_id,
        'inspection_valid_until' => now()->addDays(60),
    ]);
    // No inspection date — should NOT be counted
    Vehicle::factory()->create([
        'tenant_id' => $user->tenant_id,
        'inspection_valid_until' => null,
    ]);

    $svc = new FleetSummaryService;
    $kpi = $svc->getFleetKpi($user->tenant_id);

    expect($kpi['total_vehicles'])->toBe(3)
        ->and($kpi['inspection_due_30d'])->toBe(1);
});

test('dashboard page renders fleet summary widget', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertSuccessful()->assertSee(__('Fleet summary'));
});
