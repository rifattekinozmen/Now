<?php

use App\Models\Customer;
use App\Models\FuelIntake;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Logistics\AuditAiEvaluationService;

test('fuel evaluation skips when expected liters missing', function () {
    $svc = new AuditAiEvaluationService;
    $out = $svc->evaluateFuelVolumeAgainstExpected(100.0, null);

    expect($out['status'])->toBe('skipped')
        ->and($out['flagged'])->toBeFalse()
        ->and($out['reasons'])->toBe([]);
});

test('fuel evaluation flags when deviation exceeds threshold', function () {
    $svc = new AuditAiEvaluationService;
    $out = $svc->evaluateFuelVolumeAgainstExpected(120.0, 100.0, 15.0);

    expect($out['status'])->toBe('flagged')
        ->and($out['flagged'])->toBeTrue()
        ->and($out['reasons'])->not->toBeEmpty();
});

test('fuel evaluation ok when within threshold', function () {
    $svc = new AuditAiEvaluationService;
    $out = $svc->evaluateFuelVolumeAgainstExpected(110.0, 100.0, 15.0);

    expect($out['status'])->toBe('ok')
        ->and($out['flagged'])->toBeFalse();
});

test('freight quote skips without reference', function () {
    $svc = new AuditAiEvaluationService;
    $out = $svc->evaluateFreightQuote(['quoted_freight' => 1000.0]);

    expect($out['status'])->toBe('skipped');
});

test('freight quote flags above twenty percent deviation', function () {
    $svc = new AuditAiEvaluationService;
    $out = $svc->evaluateFreightQuote([
        'quoted_freight' => 130.0,
        'reference_freight' => 100.0,
    ]);

    expect($out['status'])->toBe('flagged')
        ->and($out['flagged'])->toBeTrue();
});

test('freight outlier summary flags orders far from median in tenant', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'freight_amount' => 900,
        'currency_code' => 'TRY',
        'order_number' => 'OUT-MEDIAN',
    ]);

    $svc = new AuditAiEvaluationService;
    $out = $svc->summarizeFreightOutliersAgainstMedian((int) $tenant->id, null, null);

    expect($out['evaluated_orders'])->toBe(3)
        ->and($out['flagged'])->not->toBeEmpty()
        ->and(collect($out['flagged'])->pluck('order_number')->contains('OUT-MEDIAN'))->toBeTrue();
});

test('fuel intake summary flags large deviation vs expected liters by km', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 40,
        'odometer_km' => 100_000,
        'recorded_at' => now()->subHours(2),
    ]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 500,
        'odometer_km' => 100_200,
        'recorded_at' => now(),
    ]);

    $svc = new AuditAiEvaluationService;
    $out = $svc->summarizeFuelIntakeAnomalies((int) $tenant->id);

    expect($out['evaluated_pairs'])->toBe(1)
        ->and($out['flagged'])->not->toBeEmpty();
});
