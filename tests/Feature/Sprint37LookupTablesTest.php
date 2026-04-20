<?php

use App\Models\Customer;
use App\Models\MaterialCode;
use App\Models\Order;
use App\Models\TaxOffice;
use App\Models\Tenant;

test('material code can be created and retrieved with active scope', function () {
    $mc = MaterialCode::create([
        'code' => 'TEST-001',
        'name' => 'Test Material',
        'category' => 'cement',
        'unit' => 'ton',
        'is_adr' => false,
        'is_active' => true,
    ]);

    expect(MaterialCode::active()->where('code', 'TEST-001')->exists())->toBeTrue();
    expect($mc->categoryLabel())->toBe('Cement');
});

test('inactive material code is excluded from active scope', function () {
    MaterialCode::create([
        'code' => 'TEST-002',
        'name' => 'Inactive Material',
        'category' => 'other',
        'unit' => 'ton',
        'is_active' => false,
    ]);

    expect(MaterialCode::active()->where('code', 'TEST-002')->exists())->toBeFalse();
});

test('material code can be linked to an order', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $mc = MaterialCode::create([
        'code' => 'CLN-9999',
        'name' => 'Klinker Test',
        'category' => 'raw_material',
        'unit' => 'ton',
        'is_active' => true,
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'material_code_id' => $mc->id,
    ]);

    expect($order->fresh()->materialCode->code)->toBe('CLN-9999');
});

test('tax office can be filtered by city using byCity scope', function () {
    TaxOffice::firstOrCreate(
        ['code' => 'X-IST-001'],
        ['name' => 'Kadıköy Test VD', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'is_active' => true]
    );
    TaxOffice::firstOrCreate(
        ['code' => 'X-ANK-001'],
        ['name' => 'Çankaya Test VD', 'city' => 'Ankara', 'district' => 'Çankaya', 'is_active' => true]
    );

    $istanbul = TaxOffice::byCity('İstanbul')->pluck('city')->unique()->all();

    expect($istanbul)->toContain('İstanbul')
        ->and($istanbul)->not->toContain('Ankara');
});

test('customer can have a tax office assigned and relation works', function () {
    $tenant = Tenant::factory()->create();
    $office = TaxOffice::firstOrCreate(
        ['code' => 'X-BRS-TEST'],
        ['name' => 'Bursa Test VD', 'city' => 'Bursa', 'district' => 'Nilüfer', 'is_active' => true]
    );

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'tax_office_id' => $office->id,
    ]);

    expect($customer->fresh()->taxOffice->code)->toBe('X-BRS-TEST');
});
