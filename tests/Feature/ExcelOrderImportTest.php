<?php

use App\Models\Customer;
use App\Models\User;
use App\Services\Logistics\ExcelImportService;

test('order import creates row for known customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'legal_name' => 'ImportTest Müşteri',
    ]);

    $path = sys_get_temp_dir().'/orders-import-'.uniqid('', true).'.csv';
    file_put_contents($path, "Ünvan,Para Birimi,SAS\nImportTest Müşteri,TRY,SAS-1\n");

    $result = app(ExcelImportService::class)->importOrdersFromPath($path, (int) $user->tenant_id);
    @unlink($path);

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();
});
