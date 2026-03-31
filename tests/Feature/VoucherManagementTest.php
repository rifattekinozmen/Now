<?php

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\CashRegister;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Finance\VoucherApprovalService;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    // Seed permissions
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.vouchers.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s vouchers', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $registerA = CashRegister::factory()->create(['tenant_id' => $tenantA->id]);
    $registerB = CashRegister::factory()->create(['tenant_id' => $tenantB->id]);

    Voucher::factory()->create(['tenant_id' => $tenantA->id, 'cash_register_id' => $registerA->id]);
    $voucherB = Voucher::factory()->create(['tenant_id' => $tenantB->id, 'cash_register_id' => $registerB->id]);

    // Act as userA → TenantContext::id() returns tenantA->id
    $this->actingAs($userA);

    // Query with global scope — should NOT see tenant B's voucher
    $vouchers = Voucher::query()->get();
    expect($vouchers->pluck('id'))->not->toContain($voucherB->id);
})->group('isolation');

// ─────────────────────────────────────────────
// MAKER-CHECKER: Non-admin cannot approve
// ─────────────────────────────────────────────

it('non-admin user cannot approve a voucher via gate', function (): void {
    $tenant   = Tenant::factory()->create();
    // withoutLogisticsRole: user has NO tenant-user role (no auto-logistics.admin)
    $user     = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.vouchers.write'); // can write but NOT admin
    $user->forgetCachedPermissions();
    $user = $user->fresh();

    $register = CashRegister::factory()->create(['tenant_id' => $tenant->id]);
    $voucher  = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'status'           => VoucherStatus::Pending->value,
    ]);

    // Verify user does NOT have logistics.admin
    expect($user->hasPermissionTo('logistics.admin'))->toBeFalse();

    // Test VoucherPolicy::approve() directly
    $policy = new \App\Policies\VoucherPolicy();
    expect($policy->approve($user, $voucher))->toBeFalse();
})->group('maker-checker');

it('logistics.admin can approve a pending voucher via gate', function (): void {
    $tenant = Tenant::factory()->create();
    $admin  = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create(['tenant_id' => $tenant->id]);
    $voucher  = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'status'           => VoucherStatus::Pending->value,
    ]);

    expect($admin->can('approve', $voucher))->toBeTrue();
})->group('maker-checker');

// ─────────────────────────────────────────────
// VoucherApprovalService
// ─────────────────────────────────────────────

it('approve() updates cash register balance for income voucher', function (): void {
    $tenant   = Tenant::factory()->create();
    $admin    = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create([
        'tenant_id'       => $tenant->id,
        'current_balance' => 1000.00,
    ]);

    $voucher = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'type'             => VoucherType::Income->value,
        'amount'           => 500.00,
        'status'           => VoucherStatus::Pending->value,
    ]);

    $service = new VoucherApprovalService();
    $service->approve($voucher, $admin);

    $register->refresh();
    $voucher->refresh();

    expect((float) $register->current_balance)->toBe(1500.00)
        ->and($voucher->status->isApproved())->toBeTrue()
        ->and($voucher->approved_by)->toBe($admin->id)
        ->and($voucher->approved_at)->not->toBeNull();
})->group('service');

it('approve() deducts cash register balance for expense voucher', function (): void {
    $tenant   = Tenant::factory()->create();
    $admin    = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create([
        'tenant_id'       => $tenant->id,
        'current_balance' => 2000.00,
    ]);

    $voucher = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'type'             => VoucherType::Expense->value,
        'amount'           => 750.00,
        'status'           => VoucherStatus::Pending->value,
    ]);

    $service = new VoucherApprovalService();
    $service->approve($voucher, $admin);

    $register->refresh();
    expect((float) $register->current_balance)->toBe(1250.00);
})->group('service');

it('approve() throws if expense would make balance negative', function (): void {
    $tenant   = Tenant::factory()->create();
    $admin    = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create([
        'tenant_id'       => $tenant->id,
        'current_balance' => 100.00,
    ]);

    $voucher = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'type'             => VoucherType::Expense->value,
        'amount'           => 500.00,
        'status'           => VoucherStatus::Pending->value,
    ]);

    $service = new VoucherApprovalService();

    expect(fn () => $service->approve($voucher, $admin))
        ->toThrow(\RuntimeException::class, 'Insufficient balance');
})->group('service');

it('approve() throws if voucher is already approved', function (): void {
    $tenant  = Tenant::factory()->create();
    $admin   = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create(['tenant_id' => $tenant->id]);
    $voucher  = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'status'           => VoucherStatus::Approved->value,
    ]);

    $service = new VoucherApprovalService();

    expect(fn () => $service->approve($voucher, $admin))
        ->toThrow(\RuntimeException::class);
})->group('service');

it('reject() sets status to rejected and records reason', function (): void {
    $tenant  = Tenant::factory()->create();
    $admin   = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $register = CashRegister::factory()->create(['tenant_id' => $tenant->id]);
    $voucher  = Voucher::factory()->create([
        'tenant_id'        => $tenant->id,
        'cash_register_id' => $register->id,
        'status'           => VoucherStatus::Pending->value,
        'amount'           => 300.00,
    ]);

    $originalBalance = (float) CashRegister::find($register->id)->current_balance;

    $service = new VoucherApprovalService();
    $service->reject($voucher, $admin, 'Invalid receipt');

    $voucher->refresh();
    $register->refresh();

    expect($voucher->status)->toBe(VoucherStatus::Rejected)
        ->and($voucher->rejection_reason)->toBe('Invalid receipt')
        ->and((float) $register->current_balance)->toBe($originalBalance, 'Balance should NOT change on reject');
})->group('service');
