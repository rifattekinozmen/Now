<?php

use App\Models\DeliveryNumber;
use App\Models\Tenant;
use App\Services\Logistics\ExcelImportService;

test('import delivery pins from csv creates rows and skips duplicates', function () {
    $tenant = Tenant::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'pins');
    $csv = "pin_code,sas_no\nP-100,SAS-A\nP-101,\nP-100,\n";
    file_put_contents($path, $csv);

    $excel = new ExcelImportService;
    $result = $excel->importDeliveryPinsFromPath($path, $tenant->id);

    expect($result['created'])->toBe(2)
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
