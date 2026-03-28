<?php

use App\Models\Customer;
use App\Models\Tenant;
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
