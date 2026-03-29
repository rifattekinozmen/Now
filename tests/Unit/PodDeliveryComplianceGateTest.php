<?php

use App\Services\Logistics\PodDeliveryComplianceGate;

test('strict mode off allows empty pod', function () {
    config(['logistics.ipod.strict' => false]);

    app(PodDeliveryComplianceGate::class)->assertDeliveredProofAllowed(null);
    app(PodDeliveryComplianceGate::class)->assertDeliveredProofAllowed([]);

    expect(true)->toBeTrue();
});

test('strict mode on requires signature latitude longitude and photo path', function () {
    config(['logistics.ipod.strict' => true]);

    app(PodDeliveryComplianceGate::class)->assertDeliveredProofAllowed([
        'signature_data_url' => 'data:image/png;base64,xx',
        'latitude' => 36.0,
        'longitude' => 35.0,
        'photo_storage_path' => 'pod-delivery-photos/1/2.jpg',
    ]);

    expect(true)->toBeTrue();
});

test('strict mode on rejects missing signature', function () {
    config(['logistics.ipod.strict' => true]);

    app(PodDeliveryComplianceGate::class)->assertDeliveredProofAllowed([
        'signature_data_url' => '  ',
        'latitude' => 1,
        'longitude' => 2,
        'photo_storage_path' => 'x',
    ]);
})->throws(InvalidArgumentException::class);

test('strict mode on rejects missing photo path', function () {
    config(['logistics.ipod.strict' => true]);

    app(PodDeliveryComplianceGate::class)->assertDeliveredProofAllowed([
        'signature_data_url' => 'data:image/png;base64,xx',
        'latitude' => 1,
        'longitude' => 2,
        'photo_storage_path' => '',
    ]);
})->throws(InvalidArgumentException::class);
