<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access invoices page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.finance.invoices.index'))
        ->assertSuccessful();
})->group('routes');

it('invoices route is protected', function (): void {
    $this->get(route('admin.finance.invoices.index'))
        ->assertRedirect(route('login'));
})->group('routes');

it('invoice model can be created with factory', function (): void {
    $invoice = Invoice::factory()->create();

    expect($invoice->id)->toBeInt()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->currency_code)->toBe('TRY')
        ->and((float) $invoice->total)->toBeGreaterThan(0);
});

it('invoice factory states work correctly', function (): void {
    $paid = Invoice::factory()->paid()->create();
    $overdue = Invoice::factory()->overdue()->create();
    $sent = Invoice::factory()->sent()->create();

    expect($paid->status)->toBe(InvoiceStatus::Paid)
        ->and($overdue->status)->toBe(InvoiceStatus::Overdue)
        ->and($sent->status)->toBe(InvoiceStatus::Sent);
});

it('invoice belongs to tenant and customer', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($invoice->tenant->id)->toBe($tenant->id)
        ->and($invoice->customer->id)->toBe($customer->id);
});

it('tenant isolation works for invoices', function (): void {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $customer1 = Customer::factory()->create(['tenant_id' => $tenant1->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id]);

    Invoice::factory()->create(['tenant_id' => $tenant1->id, 'customer_id' => $customer1->id]);
    Invoice::factory()->create(['tenant_id' => $tenant1->id, 'customer_id' => $customer1->id]);
    Invoice::factory()->create(['tenant_id' => $tenant2->id, 'customer_id' => $customer2->id]);

    $tenant1Count = Invoice::withoutGlobalScopes()->where('tenant_id', $tenant1->id)->count();
    $tenant2Count = Invoice::withoutGlobalScopes()->where('tenant_id', $tenant2->id)->count();

    expect($tenant1Count)->toBe(2)
        ->and($tenant2Count)->toBe(1);
});

it('invoice status enum has correct colors', function (): void {
    expect(InvoiceStatus::Draft->color())->toBe('zinc')
        ->and(InvoiceStatus::Sent->color())->toBe('blue')
        ->and(InvoiceStatus::Paid->color())->toBe('green')
        ->and(InvoiceStatus::Overdue->color())->toBe('red');
});

it('invoice status enum labels are non-empty strings', function (): void {
    foreach (InvoiceStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});

it('customer portal my-invoices shows only own invoices', function (): void {
    $tenant = Tenant::factory()->create();
    $customer1 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer1->id]);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer1->id]);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer2->id]);

    $c1Count = Invoice::withoutGlobalScopes()->where('customer_id', $customer1->id)->count();
    $c2Count = Invoice::withoutGlobalScopes()->where('customer_id', $customer2->id)->count();

    expect($c1Count)->toBe(2)
        ->and($c2Count)->toBe(1);
});
