<?php

use App\Authorization\LogisticsPermission;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => LogisticsPermission::ADMIN, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::VIEW, 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// Payments CSV
// ─────────────────────────────────────────────

it('admin can download payments CSV', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Payment::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('admin.finance.payments.export.csv'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
})->group('routes');

it('viewer can also download payments CSV', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo(LogisticsPermission::VIEW);

    $this->actingAs($viewer)
        ->get(route('admin.finance.payments.export.csv'))
        ->assertOk();
})->group('routes');

it('unauthenticated user is redirected from payments CSV', function (): void {
    $this->get(route('admin.finance.payments.export.csv'))
        ->assertRedirect();
})->group('routes');

it('payments CSV contains expected headers', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Payment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.finance.payments.export.csv'));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('payment_date')
        ->and($content)->toContain('amount')
        ->and($content)->toContain('currency_code');
})->group('behaviour');

// ─────────────────────────────────────────────
// Vouchers CSV
// ─────────────────────────────────────────────

it('admin can download vouchers CSV', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Voucher::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('admin.finance.vouchers.export.csv'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
})->group('routes');

it('viewer can also download vouchers CSV', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo(LogisticsPermission::VIEW);

    $this->actingAs($viewer)
        ->get(route('admin.finance.vouchers.export.csv'))
        ->assertOk();
})->group('routes');

it('unauthenticated user is redirected from vouchers CSV', function (): void {
    $this->get(route('admin.finance.vouchers.export.csv'))
        ->assertRedirect();
})->group('routes');

it('vouchers CSV contains expected headers', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Voucher::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.finance.vouchers.export.csv'));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('voucher_date')
        ->and($content)->toContain('amount')
        ->and($content)->toContain('currency_code');
})->group('behaviour');
