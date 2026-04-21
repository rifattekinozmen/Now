<?php

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Delivery\DeliveryReportImportService;
use App\Support\DeliveryImportPhp;
use Spatie\Permission\Models\Permission;

it('delivery import php reports zip availability as boolean', function (): void {
    expect(DeliveryImportPhp::isZipAvailableForXlsx())->toBeBool();
});

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('admin can access delivery imports index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.delivery-imports.index'))
        ->assertSuccessful();
});

it('viewer can access delivery imports index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.delivery-imports.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from delivery imports', function (): void {
    $this->get(route('admin.delivery-imports.index'))
        ->assertRedirect();
});

it('cannot read another tenant delivery imports', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    DeliveryImport::factory()->create(['tenant_id' => $tenantA->id]);
    $importB = DeliveryImport::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $imports = DeliveryImport::query()->get();
    expect($imports->pluck('id'))->not->toContain($importB->id);
});

it('delivery import has correct enum cast', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => DeliveryImportStatus::Pending->value,
    ]);

    expect($import->fresh()->status)->toBe(DeliveryImportStatus::Pending);
});

it('delivery import factory processed state works', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->processed()->create(['tenant_id' => $tenant->id]);

    expect($import->status)->toBe(DeliveryImportStatus::Processed);
    expect($import->unmatched_count)->toBe(0);
});

it('admin cannot delete another tenant delivery import', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $importB = DeliveryImport::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);
    $this->assertFalse($userA->can('delete', $importB));
});

it('admin can delete own tenant delivery import', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $import = DeliveryImport::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user);
    $this->assertTrue($user->can('delete', $import));
});

it('admin can view delivery import detail page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $import = DeliveryImport::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('admin.delivery-imports.show', $import))
        ->assertSuccessful();
});

it('viewer can view delivery import detail page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $import = DeliveryImport::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('admin.delivery-imports.show', $import))
        ->assertSuccessful();
});

it('cannot view another tenant delivery import detail', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $importB = DeliveryImport::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA)
        ->get(route('admin.delivery-imports.show', $importB))
        ->assertNotFound();
});

it('admin can download material pivot and invoice line csv for own tenant import', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $import = DeliveryImport::factory()->create(['tenant_id' => $tenant->id]);

    $pivot = $this->actingAs($user)
        ->get(route('admin.delivery-imports.material-pivot.csv', $import));

    $pivot->assertOk();
    expect((string) $pivot->headers->get('content-disposition'))->toContain('attachment');
    expect($pivot->streamedContent())->toContain('TARİH');

    $invoice = $this->actingAs($user)
        ->get(route('admin.delivery-imports.invoice-lines.csv', $import));

    $invoice->assertOk();
    expect((string) $invoice->headers->get('content-disposition'))->toContain('attachment');
    expect($invoice->streamedContent())->toContain('rota');
});

it('cannot download delivery import csv for another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $importB = DeliveryImport::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA)
        ->get(route('admin.delivery-imports.material-pivot.csv', $importB))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('admin.delivery-imports.invoice-lines.csv', $importB))
        ->assertNotFound();
});

it('can view delivery import when active tenant matches import tenant', function (): void {
    $tenantHome = Tenant::factory()->create();
    $tenantActive = Tenant::factory()->create();

    $user = User::factory()->create([
        'tenant_id' => $tenantHome->id,
        'active_tenant_id' => $tenantActive->id,
    ]);
    $user->givePermissionTo('logistics.admin');

    $import = DeliveryImport::factory()->create(['tenant_id' => $tenantActive->id]);

    expect($user->can('view', $import))->toBeTrue();

    $this->actingAs($user)
        ->get(route('admin.delivery-imports.show', $import))
        ->assertSuccessful();
});

it('denies viewing delivery import when active tenant does not match import tenant', function (): void {
    $tenantHome = Tenant::factory()->create();
    $tenantActive = Tenant::factory()->create();

    $user = User::factory()->create([
        'tenant_id' => $tenantHome->id,
        'active_tenant_id' => $tenantActive->id,
    ]);
    $user->givePermissionTo('logistics.admin');

    $importOther = DeliveryImport::factory()->create(['tenant_id' => $tenantHome->id]);

    expect($user->can('view', $importOther))->toBeFalse();
});

it('builds excel column layout from import meta for left to right display', function (): void {
    $tenant = Tenant::factory()->create();
    $import = DeliveryImport::factory()->create([
        'tenant_id' => $tenant->id,
        'meta' => [
            'delivery_excel_layout' => [
                'excel_headers' => ['A', 'B'],
                'mapping_expected_to_excel' => [0 => 1, 1 => 0],
                'header_row_1based' => 3,
            ],
        ],
    ]);

    $layout = app(DeliveryReportImportService::class)->getExcelColumnLayoutForDisplay($import);

    expect($layout)->toHaveCount(2)
        ->and($layout[0]['excel_col'])->toBe(0)
        ->and($layout[0]['header'])->toBe('A')
        ->and($layout[0]['expected_index'])->toBe(1)
        ->and($layout[1]['excel_col'])->toBe(1)
        ->and($layout[1]['header'])->toBe('B')
        ->and($layout[1]['expected_index'])->toBe(0);
});
