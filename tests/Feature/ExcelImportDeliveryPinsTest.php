<?php

use App\Enums\DeliveryNumberStatus;
use App\Models\DeliveryNumber;
use App\Models\Tenant;
use App\Services\Logistics\ExcelImportService;

test('import delivery pins from csv creates rows and skips duplicates', function () {
    $tenant = Tenant::factory()->create();
    DeliveryNumber::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'pin_code' => 'P-100',
        'sas_no' => 'SAS-EXIST',
        'status' => DeliveryNumberStatus::Available,
        'order_id' => null,
        'shipment_id' => null,
        'assigned_at' => null,
        'used_at' => null,
        'meta' => null,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'pins');
    $csv = "pin_code,sas_no\nP-100,SAS-A\nP-101,\n";
    file_put_contents($path, $csv);

    $excel = new ExcelImportService;
    $result = $excel->importDeliveryPinsFromPath($path, $tenant->id);

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->toHaveCount(0);

    expect(DeliveryNumber::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);

    @unlink($path);
});

test('import reports duplicate pin within same file', function () {
    $tenant = Tenant::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'pins');
    $csv = "PIN Kodu\nX1\nX1\n";
    file_put_contents($path, $csv);

    $excel = new ExcelImportService;
    $result = $excel->importDeliveryPinsFromPath($path, $tenant->id);

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->not->toBeEmpty();

    @unlink($path);
});
