<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

// ─────────────────────────────────────────────
// ROUTE — access control
// ─────────────────────────────────────────────

it('customer user can access customer dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.dashboard'))
        ->assertSuccessful();
})->group('routes');

it('customer user can access orders index', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.orders.index'))
        ->assertSuccessful();
})->group('routes');

it('customer user can access shipments index', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.shipments.index'))
        ->assertSuccessful();
})->group('routes');

it('customer user can access my payments', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.payments.index'))
        ->assertSuccessful();
})->group('routes');

it('user without customer_id is forbidden from customer dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('customer.dashboard'))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from customer portal', function (): void {
    $this->get(route('customer.dashboard'))->assertRedirect();
    $this->get(route('customer.orders.index'))->assertRedirect();
    $this->get(route('customer.shipments.index'))->assertRedirect();
    $this->get(route('customer.payments.index'))->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('customer only sees their own orders', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
    Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $otherCustomer->id]);

    Livewire::actingAs($user)
        ->test('pages::customer.orders-index')
        ->assertSee($customer->id)
        ->call('$refresh');

    // orders computed returns only customer's orders
    $component = Livewire::actingAs($user)->test('pages::customer.orders-index');
    expect($component->get('orders')->total())->toBe(1);
})->group('isolation');

it('customer only sees payments linked to their invoices', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $invoiceMine = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    $invoiceOther = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $otherCustomer->id,
    ]);

    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'payable_type' => Invoice::class,
        'payable_id' => $invoiceMine->id,
        'reference_no' => 'PAY-PORTAL-MINE',
    ]);
    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'payable_type' => Invoice::class,
        'payable_id' => $invoiceOther->id,
        'reference_no' => 'PAY-PORTAL-OTHER',
    ]);

    Livewire::actingAs($user)
        ->test('pages::customer.my-payments')
        ->assertSee('PAY-PORTAL-MINE')
        ->assertDontSee('PAY-PORTAL-OTHER');
})->group('isolation');

it('customer only sees their own shipments', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $ownOrder = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
    $otherOrder = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $otherCustomer->id]);

    Shipment::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $ownOrder->id]);
    Shipment::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $otherOrder->id]);

    $component = Livewire::actingAs($user)->test('pages::customer.shipments-index');
    expect($component->get('shipments')->total())->toBe(1);
})->group('isolation');
