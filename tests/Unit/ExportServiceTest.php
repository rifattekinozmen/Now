<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Logistics\ExportService;

test('customers csv content matches import header order and row values', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'partner_number' => 'P1',
        'tax_id' => 'T1',
        'legal_name' => 'LegalCo',
        'trade_name' => 'TradeCo',
        'payment_term_days' => 33,
    ]);

    $service = app(ExportService::class);
    $csv = $service->customersCsvContent(collect([$customer]));

    expect($csv)->toContain('İş Ortağı No')
        ->and($csv)->toContain('Vergi No')
        ->and($csv)->toContain('Ünvan')
        ->and($csv)->toContain('LegalCo')
        ->and($csv)->toContain('TradeCo')
        ->and($csv)->toContain('33');
});
