<?php

use App\Models\FuelPrice;
use App\Models\Tenant;
use App\Services\Logistics\ExcelImportService;

test('fuel price import mapping has required keys', function () {
    $svc = new ExcelImportService;
    $mapping = $svc->getFuelPriceImportMapping();

    expect($mapping)->toHaveKey('Yakıt Tipi')
        ->and($mapping)->toHaveKey('Fiyat')
        ->and($mapping)->toHaveKey('Para Birimi')
        ->and($mapping)->toHaveKey('Kayıt Tarihi')
        ->and($mapping)->toHaveKey('Tarih')
        ->and($mapping)->toHaveKey('Motorin TL/Lt');
});

test('fuel price csv import creates records for tenant', function () {
    $tenant = Tenant::factory()->create();

    $csv = "Yakıt Tipi,Fiyat,Para Birimi,Kayıt Tarihi,Kaynak,Bölge\ndiesel,45.5,TRY,2026-03-30,TotalEnergies,İstanbul\ngasoline,38.2,TRY,2026-03-30,,\n";
    $path = tempnam(sys_get_temp_dir(), 'fp_test_').'.csv';
    file_put_contents($path, $csv);

    $svc = new ExcelImportService;
    $result = $svc->importFuelPricesFromPath($path, $tenant->id);
    unlink($path);

    expect($result['created'])->toBe(2)
        ->and($result['errors'])->toBeEmpty();

    expect(FuelPrice::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
    expect(FuelPrice::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('fuel_type', 'diesel')->value('source'))->toBe('TotalEnergies');
});

test('fuel price import rejects invalid fuel type', function () {
    $tenant = Tenant::factory()->create();

    $csv = "Yakıt Tipi,Fiyat,Para Birimi,Kayıt Tarihi\nkerosene,45.5,TRY,2026-03-30\n";
    $path = tempnam(sys_get_temp_dir(), 'fp_test_').'.csv';
    file_put_contents($path, $csv);

    $svc = new ExcelImportService;
    $result = $svc->importFuelPricesFromPath($path, $tenant->id);
    unlink($path);

    expect($result['created'])->toBe(0)
        ->and($result['errors'])->toHaveCount(1);
});

test('fuel price import does not cross tenant boundary', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $csv = "Yakıt Tipi,Fiyat,Para Birimi,Kayıt Tarihi\ndiesel,45.0,TRY,2026-03-30\n";
    $path = tempnam(sys_get_temp_dir(), 'fp_test_').'.csv';
    file_put_contents($path, $csv);

    $svc = new ExcelImportService;
    $svc->importFuelPricesFromPath($path, $tenantA->id);
    unlink($path);

    expect(FuelPrice::query()->withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(0);
});

test('fuel price archive style csv import creates three fuel records from single row', function () {
    $tenant = Tenant::factory()->create();

    $csv = "Tarih,Excellium Kurşunsuz 95 TL/Lt,Motorin TL/Lt,Otogaz TL/Lt\n01.04.2026,64.36,79.38,0.00\n";
    $path = tempnam(sys_get_temp_dir(), 'fp_archive_test_').'.csv';
    file_put_contents($path, $csv);

    $svc = new ExcelImportService;
    $result = $svc->importFuelPricesFromPath($path, $tenant->id);
    unlink($path);

    expect($result['created'])->toBe(3)
        ->and($result['errors'])->toBeEmpty();

    $rows = FuelPrice::query()
        ->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereDate('recorded_at', '2026-04-01')
        ->get();

    expect($rows->count())->toBe(3)
        ->and($rows->where('fuel_type', 'diesel')->first()?->price)->toBe('79.3800')
        ->and($rows->where('fuel_type', 'gasoline')->first()?->price)->toBe('64.3600')
        ->and($rows->where('fuel_type', 'lpg')->first()?->price)->toBe('0.0000')
        ->and($rows->first()?->source)->toBe('GüzelEnerji API');
});
