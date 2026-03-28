<?php

use App\Services\Logistics\FreightCalculationService;

test('estimate scales with distance tonnage and default rate', function () {
    $service = new FreightCalculationService;

    expect($service->estimate(100, 26))->toBe('65.00');
});

test('estimate uses minimum tonnage floor', function () {
    $service = new FreightCalculationService;

    expect($service->estimate(100, 0))->toBe('0.25');
});

test('zero distance yields zero freight', function () {
    $service = new FreightCalculationService;

    expect($service->estimate(0, 26))->toBe('0.00');
});
