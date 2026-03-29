<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Tests\TestCase;

test('guests cannot access admin logistics routes', function () {
    /** @var TestCase $this */
    $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
    $this->get(route('admin.customers.export.csv'))->assertRedirect(route('login'));
    $this->get(route('admin.customers.template.xlsx'))->assertRedirect(route('login'));
    $this->get(route('admin.customers.show', 1))->assertRedirect(route('login'));
    $this->get(route('admin.vehicles.index'))->assertRedirect(route('login'));
    $this->get(route('admin.vehicles.template.xlsx'))->assertRedirect(route('login'));
    $this->get(route('admin.employees.index'))->assertRedirect(route('login'));
    $this->get(route('admin.employees.template.xlsx'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.show', 1))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.index'))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.show', 1))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.qr.svg', 1))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.pod.signature', 1))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.pod.print', 1))->assertRedirect(route('login'));
    $this->get(route('admin.delivery-numbers.index'))->assertRedirect(route('login'));
    $this->get(route('admin.delivery-numbers.template.xlsx'))->assertRedirect(route('login'));
    $this->get(route('admin.finance.index'))->assertRedirect(route('login'));
    $this->get(route('admin.finance.reports'))->assertRedirect(route('login'));
    $this->get(route('admin.finance.payment-due-calendar'))->assertRedirect(route('login'));
    $this->get(route('admin.finance.bank-statement-csv'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.export.finance.csv'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.export.logo.xml'))->assertRedirect(route('login'));
});

test('admin full-page layout is not nested twice', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $html = $this->get(route('admin.vehicles.index'))->assertSuccessful()->getContent();

    $marker = 'https://github.com/laravel/livewire-starter-kit';
    expect(substr_count($html, $marker))->toBe(1);
});

test('authenticated users can access admin logistics routes', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.customers.index'))->assertSuccessful();
    $this->get(route('admin.customers.export.csv'))->assertSuccessful();
    $this->get(route('admin.customers.template.xlsx'))->assertSuccessful();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $this->get(route('admin.customers.show', $customer))->assertSuccessful();
    $this->get(route('admin.vehicles.index'))->assertSuccessful();
    $this->get(route('admin.vehicles.template.xlsx'))->assertSuccessful();
    $this->get(route('admin.employees.index'))->assertSuccessful();
    $this->get(route('admin.employees.template.xlsx'))->assertSuccessful();
    $this->get(route('admin.orders.index'))->assertSuccessful();
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $this->get(route('admin.orders.show', $order))->assertSuccessful();
    $this->get(route('admin.shipments.index'))->assertSuccessful();
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);
    $this->get(route('admin.shipments.show', $shipment))->assertSuccessful();
    $this->get(route('admin.shipments.qr.svg', $shipment))->assertSuccessful();
    $this->get(route('admin.shipments.pod.print', $shipment))->assertSuccessful();
    $this->get(route('admin.delivery-numbers.index'))->assertSuccessful();
    $this->get(route('admin.delivery-numbers.template.xlsx'))->assertSuccessful();
    $this->get(route('admin.finance.index'))->assertSuccessful();
    $this->get(route('admin.finance.reports'))->assertSuccessful();
    $this->get(route('admin.finance.payment-due-calendar'))->assertSuccessful();
    $this->get(route('admin.finance.bank-statement-csv'))->assertSuccessful();
    $this->get(route('admin.orders.export.finance.csv'))->assertSuccessful();
    $this->get(route('admin.orders.export.logo.xml'))->assertSuccessful();
});
