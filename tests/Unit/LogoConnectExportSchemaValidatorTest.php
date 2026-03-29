<?php

use App\Models\Order;
use App\Services\Integrations\Logo\LogoConnectExportSchemaValidator;
use App\Services\Integrations\Logo\LogoErpExportService;

test('logo export xml passes schema validator', function () {
    $order = Order::factory()->create();
    $order->load('customer');

    $xml = (new LogoErpExportService)->buildOrdersConnectXml([$order]);
    $result = (new LogoConnectExportSchemaValidator)->validate($xml);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

test('invalid xml fails validation', function () {
    $result = (new LogoConnectExportSchemaValidator)->validate('not xml');

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('invalid_xml');
});
