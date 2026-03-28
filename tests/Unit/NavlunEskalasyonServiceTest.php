<?php

use App\Services\Logistics\NavlunEskalasyonService;

test('relative change is absolute ratio of previous price', function () {
    $svc = new NavlunEskalasyonService;

    expect($svc->relativeChangeAbs(100.0, 105.0))->toBe(0.05)
        ->and($svc->relativeChangeAbs(100.0, 95.0))->toBe(0.05);
});

test('exceeds threshold when change strictly greater than ratio', function () {
    $svc = new NavlunEskalasyonService;

    expect($svc->exceedsThreshold(100.0, 105.1, 0.05))->toBeTrue()
        ->and($svc->exceedsThreshold(100.0, 105.0, 0.05))->toBeFalse()
        ->and($svc->exceedsThreshold(100.0, 94.9, 0.05))->toBeTrue();
});

test('zero previous price yields full jump when new price positive', function () {
    $svc = new NavlunEskalasyonService;

    expect($svc->relativeChangeAbs(0.0, 10.0))->toBe(1.0)
        ->and($svc->exceedsThreshold(0.0, 10.0, 0.05))->toBeTrue();
});
