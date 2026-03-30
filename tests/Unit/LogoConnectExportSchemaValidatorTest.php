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

test('xml with wrong root element fails validation', function () {
    $xml = '<?xml version="1.0"?><WrongRoot schemaVersion="1"></WrongRoot>';
    $result = (new LogoConnectExportSchemaValidator)->validate($xml);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('unexpected_root_element');
});

test('xml with mismatched schema version returns error', function () {
    $xml = '<?xml version="1.0"?><LogoConnectExport schemaVersion="99"></LogoConnectExport>';
    $result = (new LogoConnectExportSchemaValidator)->validate($xml);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('schema_version_mismatch');
});

test('order missing OrderRecordId fails validation', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <LogoConnectExport schemaVersion="1">
        <Order>
            <OrderNumber>ON-001</OrderNumber>
        </Order>
    </LogoConnectExport>
    XML;

    $result = (new LogoConnectExportSchemaValidator)->validate($xml);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('order_missing_OrderRecordId');
});

test('order missing OrderNumber fails validation', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <LogoConnectExport schemaVersion="1">
        <Order>
            <OrderRecordId>42</OrderRecordId>
        </Order>
    </LogoConnectExport>
    XML;

    $result = (new LogoConnectExportSchemaValidator)->validate($xml);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('order_missing_OrderNumber');
});
