<?php

use App\Models\CbamReport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Compliance\CbamReportService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access cbam reports page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.compliance.cbam-reports'))
        ->assertOk()
        ->assertSee(__('CBAM Carbon Reports'));
});

it('cbam reports page requires authentication', function (): void {
    $this->get(route('admin.compliance.cbam-reports'))
        ->assertRedirect();
});

it('cbam service calculates co2 correctly', function (): void {
    $service = new CbamReportService;

    // 1000 km × 0.33 l/km × 2.64 kg/l = 871.2 kg CO2
    $co2 = $service->calculateCo2(1000);
    $expected = 1000 * 0.33 * 2.64;
    expect(abs($co2 - $expected))->toBeLessThan(0.1);
});

it('cbam service calculates co2 with explicit fuel', function (): void {
    $service = new CbamReportService;

    $co2 = $service->calculateCo2(500, 150); // 150 litres explicit
    $expected = 150 * 2.64;
    expect(abs($co2 - $expected))->toBeLessThan(0.1);
});

it('cbam report factory creates valid records', function (): void {
    $tenant = Tenant::factory()->create();
    $report = CbamReport::factory()->create(['tenant_id' => $tenant->id]);

    expect($report->co2_kg)->toBeGreaterThan(0);
    expect($report->status)->toBeIn(['draft', 'submitted', 'accepted']);
});

it('cbam service exports csv with correct headers', function (): void {
    $tenant = Tenant::factory()->create();
    $reports = CbamReport::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $service = new CbamReportService;
    $csv = $service->toCsv($reports);

    expect($csv)->toContain('id,report_date,shipment_id');
    $lines = explode("\n", trim($csv));
    expect(count($lines))->toBe(4); // 1 header + 3 data rows
});

it('cbam reports respect tenant isolation', function (): void {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();

    CbamReport::factory()->count(2)->create(['tenant_id' => $t1->id]);
    CbamReport::factory()->count(3)->create(['tenant_id' => $t2->id]);

    $count1 = CbamReport::withoutGlobalScopes()->where('tenant_id', $t1->id)->count();
    $count2 = CbamReport::withoutGlobalScopes()->where('tenant_id', $t2->id)->count();

    expect($count1)->toBe(2)->and($count2)->toBe(3);
});
