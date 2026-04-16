<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.vouchers.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s payments', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    Payment::factory()->create(['tenant_id' => $tenantA->id]);
    $paymentB = Payment::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $payments = Payment::query()->get();
    expect($payments->pluck('id'))->not->toContain($paymentB->id);
})->group('isolation');

// ─────────────────────────────────────────────
// CRUD — route accessible by admin
// ─────────────────────────────────────────────

it('admin can access payments index page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $response = $this->actingAs($admin)->get(route('admin.finance.payments.index'));
    $response->assertSuccessful();
})->group('routes');

it('viewer can access payments index page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $response = $this->actingAs($viewer)->get(route('admin.finance.payments.index'));
    $response->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from payments page', function (): void {
    $response = $this->get(route('admin.finance.payments.index'));
    $response->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// MAKER-CHECKER POLICY
// ─────────────────────────────────────────────

it('non-admin cannot approve a payment', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.vouchers.write');
    $user->forgetCachedPermissions();
    $user = $user->fresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::Pending->value,
    ]);

    expect($user->hasPermissionTo('logistics.admin'))->toBeFalse();

    $policy = new PaymentPolicy;
    expect($policy->approve($user, $payment))->toBeFalse();
})->group('maker-checker');

it('logistics.admin can approve a pending payment', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::Pending->value,
    ]);

    $policy = new PaymentPolicy;
    expect($policy->approve($admin, $payment))->toBeTrue();
})->group('maker-checker');

it('cannot approve a completed payment', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $payment = Payment::factory()->completed()->create(['tenant_id' => $tenant->id]);

    $policy = new PaymentPolicy;
    expect($policy->approve($admin, $payment))->toBeFalse();
})->group('maker-checker');

// ─────────────────────────────────────────────
// MODEL / FACTORY
// ─────────────────────────────────────────────

it('payment factory creates valid records', function (): void {
    $tenant = Tenant::factory()->create();
    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);

    expect($payment->id)->toBeInt()
        ->and((float) $payment->amount)->toBeGreaterThan(0)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->payment_method)->toBeInstanceOf(PaymentMethod::class);
});

it('overdue scope returns only past-due pending payments', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $this->actingAs($user);

    $overduePayment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::Pending->value,
        'due_date' => now()->subDays(5)->format('Y-m-d'),
    ]);

    $futurePayment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::Pending->value,
        'due_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $overdue = Payment::query()
        ->pending()
        ->whereNotNull('due_date')
        ->where('due_date', '<', today())
        ->get();

    expect($overdue->pluck('id'))->toContain($overduePayment->id)
        ->and($overdue->pluck('id'))->not->toContain($futurePayment->id);
});
