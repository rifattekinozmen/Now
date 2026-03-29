<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Logistics\ExcelImportService;

test('customer csv import creates rows using getMapping and normalizeRow', function () {
    $tenant = Tenant::factory()->create();
    $service = app(ExcelImportService::class);

    $csv = "İş Ortağı No,Vergi No,Ünvan,Ticari Unvan,Vade Gün\n".
        "BP001,1234567890,Acme A.Ş.,Acme,45\n";

    $path = sys_get_temp_dir().'/now-import-'.uniqid('', true).'.csv';
    file_put_contents($path, $csv);

    $result = $service->importCustomersFromPath($path, $tenant->id);
    unlink($path);

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();

    $customer = Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->legal_name)->toBe('Acme A.Ş.')
        ->and($customer->tax_id)->toBe('1234567890')
        ->and($customer->partner_number)->toBe('BP001')
        ->and($customer->trade_name)->toBe('Acme')
        ->and($customer->payment_term_days)->toBe(45);
});

test('normalizeRow maps labels to attributes', function () {
    $service = app(ExcelImportService::class);
    $mapping = $service->getCustomerImportMapping();
    $row = [
        'Ünvan' => '  Test Ltd  ',
        'Vergi No' => '987',
    ];
    $out = $service->normalizeRow($row, $mapping);
    expect($out['legal_name'])->toBe('Test Ltd')
        ->and($out['tax_id'])->toBe('987');
});

test('vehicle csv import stores vin when şasi column present', function () {
    $tenant = Tenant::factory()->create();
    $service = app(ExcelImportService::class);

    $csv = "Plaka,Şasi,Marka,Model,Muayene\n".
        "35 XLS 99,WVM12345678901234,MAN,TGX,2026-12-01\n";

    $path = sys_get_temp_dir().'/now-v-import-'.uniqid('', true).'.csv';
    file_put_contents($path, $csv);

    $result = $service->importVehiclesFromPath($path, $tenant->id);
    unlink($path);

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();

    $vehicle = Vehicle::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($vehicle)->not->toBeNull()
        ->and($vehicle->plate)->toBe('35 XLS 99')
        ->and($vehicle->vin)->toBe('WVM12345678901234');
});
