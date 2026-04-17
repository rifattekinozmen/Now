<?php

use App\Enums\ShipmentStatus;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\HR\DriverPerformanceService;

// ─────────────────────────────────────────────
// Score calculation
// ─────────────────────────────────────────────

it('gives base score of 60 for driver with no deliveries and no expired docs', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    $result = app(DriverPerformanceService::class)->scoreForEmployee($employee);

    expect($result['score'])->toBe(60)
        ->and($result['grade'])->toBe('C')
        ->and($result['deliveries_90d'])->toBe(0)
        ->and($result['expired_docs'])->toBe(0);
})->group('behaviour');

it('increases score by 4 per delivery up to 40 extra points', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    // 10 deliveries = +40 pts → score 100
    Shipment::factory()->count(10)->create([
        'tenant_id' => $tenant->id,
        'driver_employee_id' => $employee->id,
        'status' => ShipmentStatus::Delivered->value,
        'updated_at' => now()->subDays(10),
    ]);

    $result = app(DriverPerformanceService::class)->scoreForEmployee($employee);

    expect($result['score'])->toBe(100)
        ->and($result['grade'])->toBe('A')
        ->and($result['deliveries_90d'])->toBe(10);
})->group('behaviour');

it('clamps score to 100 even with more than 10 deliveries', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    Shipment::factory()->count(20)->create([
        'tenant_id' => $tenant->id,
        'driver_employee_id' => $employee->id,
        'status' => ShipmentStatus::Delivered->value,
        'updated_at' => now()->subDays(5),
    ]);

    $result = app(DriverPerformanceService::class)->scoreForEmployee($employee);

    expect($result['score'])->toBe(100);
})->group('behaviour');

it('does not count deliveries older than 90 days', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    Shipment::factory()->count(5)->create([
        'tenant_id' => $tenant->id,
        'driver_employee_id' => $employee->id,
        'status' => ShipmentStatus::Delivered->value,
        'updated_at' => now()->subDays(100),
    ]);

    $result = app(DriverPerformanceService::class)->scoreForEmployee($employee);

    expect($result['deliveries_90d'])->toBe(0)
        ->and($result['score'])->toBe(60);
})->group('behaviour');

it('clamps score to 0 when driver has many expired docs', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);

    // 5 expired docs = -100 pts → score clamped to 0
    Document::factory()->count(5)->create([
        'tenant_id' => $tenant->id,
        'documentable_type' => Employee::class,
        'documentable_id' => $employee->id,
        'expires_at' => now()->subDays(30),
    ]);

    $result = app(DriverPerformanceService::class)->scoreForEmployee($employee);

    expect($result['score'])->toBe(0)
        ->and($result['grade'])->toBe('F');
})->group('behaviour');

// ─────────────────────────────────────────────
// Leaderboard
// ─────────────────────────────────────────────

it('leaderboard returns only is_driver employees sorted by score desc', function (): void {
    $tenant = Tenant::factory()->create();

    $driverA = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);
    $driverB = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => true]);
    $nonDriver = Employee::factory()->create(['tenant_id' => $tenant->id, 'is_driver' => false]);

    // driverB gets 5 deliveries → higher score
    Shipment::factory()->count(5)->create([
        'tenant_id' => $tenant->id,
        'driver_employee_id' => $driverB->id,
        'status' => ShipmentStatus::Delivered->value,
        'updated_at' => now()->subDays(5),
    ]);

    $leaderboard = app(DriverPerformanceService::class)->leaderboard($tenant->id, 5);

    expect($leaderboard)->toHaveCount(2)
        ->and($leaderboard->first()['employee']->id)->toBe($driverB->id)
        ->and($leaderboard->first()['score'])->toBeGreaterThan($leaderboard->last()['score']);
})->group('behaviour');

it('leaderboard respects tenant isolation', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Employee::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'is_driver' => true]);
    Employee::factory()->count(2)->create(['tenant_id' => $tenantB->id, 'is_driver' => true]);

    $leaderboard = app(DriverPerformanceService::class)->leaderboard($tenantA->id, 10);

    expect($leaderboard)->toHaveCount(3);
})->group('behaviour');
