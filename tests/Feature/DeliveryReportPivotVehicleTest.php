<?php

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use App\Models\DeliveryImportRow;
use App\Models\Tenant;
use App\Services\Delivery\DeliveryReportPivotService;

/**
 * @return array<int, string>
 */
function endustriyelHammaddeRowTemplate(): array
{
    $data = array_fill(0, 45, '');
    $data[4] = '08.04.2026';
    $data[12] = 'CLN-0100';
    $data[13] = 'KLINKER GRI';
    $data[16] = '25,00';
    $data[32] = '08.04.2026';
    $data[33] = '08:00';
    $data[34] = '08.04.2026';
    $data[35] = '10:00';
    $data[37] = '34ABC123';

    return $data;
}

it('matches klinker to return with equal tonnage as full dolu-dolu', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'report_type' => 'endustriyel_hammadde',
        'status' => DeliveryImportStatus::Processed->value,
    ]);

    $k = endustriyelHammaddeRowTemplate();
    $p = endustriyelHammaddeRowTemplate();
    $p[12] = 'PETROKOK-1';
    $p[13] = 'PETROKOK MS';
    $p[16] = '25,00';
    $p[33] = '14:00';
    $p[35] = '16:00';

    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 1,
        'row_data' => $k,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 2,
        'row_data' => $p,
    ]);

    $import->load('reportRows');
    $rows = app(DeliveryReportPivotService::class)->buildVehicleDdBdReport($import);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['dd_miktar'])->toBe(25.0)
        ->and($rows[0]['bd_miktar'])->toBe(0.0);
});

it('assigns excess return tonnage to bos-dolu', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'report_type' => 'endustriyel_hammadde',
        'status' => DeliveryImportStatus::Processed->value,
    ]);

    $k = endustriyelHammaddeRowTemplate();
    $k[16] = '25,00';
    $p = endustriyelHammaddeRowTemplate();
    $p[12] = 'PETROKOK-1';
    $p[13] = 'PETROKOK MS';
    $p[16] = '26,00';

    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 1,
        'row_data' => $k,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 2,
        'row_data' => $p,
    ]);

    $import->load('reportRows');
    $rows = app(DeliveryReportPivotService::class)->buildVehicleDdBdReport($import);
    expect($rows[0]['dd_miktar'])->toBe(25.0)
        ->and($rows[0]['bd_miktar'])->toBe(1.0);
});

it('assigns excess klinker tonnage to bos-dolu', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'report_type' => 'endustriyel_hammadde',
        'status' => DeliveryImportStatus::Processed->value,
    ]);

    $k = endustriyelHammaddeRowTemplate();
    $k[16] = '26,00';
    $p = endustriyelHammaddeRowTemplate();
    $p[12] = 'PETROKOK-1';
    $p[13] = 'PETROKOK MS';
    $p[16] = '25,00';

    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 1,
        'row_data' => $k,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 2,
        'row_data' => $p,
    ]);

    $import->load('reportRows');
    $rows = app(DeliveryReportPivotService::class)->buildVehicleDdBdReport($import);
    expect($rows[0]['dd_miktar'])->toBe(25.0)
        ->and($rows[0]['bd_miktar'])->toBe(1.0);
});

it('expires klinker older than seven days before return', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'report_type' => 'endustriyel_hammadde',
        'status' => DeliveryImportStatus::Processed->value,
    ]);

    $k = endustriyelHammaddeRowTemplate();
    $k[4] = '01.04.2026';
    $k[32] = '01.04.2026';
    $k[34] = '01.04.2026';
    $k[16] = '25,00';

    $p = endustriyelHammaddeRowTemplate();
    $p[4] = '15.04.2026';
    $p[32] = '15.04.2026';
    $p[34] = '15.04.2026';
    $p[12] = 'PETROKOK-1';
    $p[13] = 'PETROKOK MS';
    $p[16] = '25,00';

    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 1,
        'row_data' => $k,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 2,
        'row_data' => $p,
    ]);

    $import->load('reportRows');
    $rows = app(DeliveryReportPivotService::class)->buildVehicleDdBdReport($import);
    expect($rows[0]['dd_miktar'])->toBe(0.0)
        ->and($rows[0]['bd_miktar'])->toBe(50.0);
});

it('sorts by entry time so fifo matches earlier klinker first', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'report_type' => 'endustriyel_hammadde',
        'status' => DeliveryImportStatus::Processed->value,
    ]);

    $kLate = endustriyelHammaddeRowTemplate();
    $kLate[33] = '12:00';
    $kLate[16] = '10,00';

    $kEarly = endustriyelHammaddeRowTemplate();
    $kEarly[33] = '06:00';
    $kEarly[16] = '10,00';

    $p = endustriyelHammaddeRowTemplate();
    $p[12] = 'PETROKOK-1';
    $p[13] = 'PETROKOK MS';
    $p[16] = '15,00';
    $p[33] = '18:00';

    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 1,
        'row_data' => $kLate,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 2,
        'row_data' => $p,
    ]);
    DeliveryImportRow::query()->create([
        'tenant_id' => $tenant->id,
        'delivery_import_id' => $import->id,
        'row_index' => 3,
        'row_data' => $kEarly,
    ]);

    $import->load('reportRows');
    $rows = app(DeliveryReportPivotService::class)->buildVehicleDdBdReport($import);
    expect($rows[0]['dd_miktar'])->toBe(15.0)
        ->and($rows[0]['bd_miktar'])->toBe(5.0);
});
