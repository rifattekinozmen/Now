<?php

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

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
