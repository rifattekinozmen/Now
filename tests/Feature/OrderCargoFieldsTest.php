<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.orders.write', 'guard_name' => 'web']);
});

test('order can store cargo type and pallet fields', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'cargo_type' => 'palletized',
        'pallet_count' => 24,
        'pallet_standard' => 'euro_80x120',
    ]);

    expect($order->cargo_type)->toBe('palletized')
        ->and($order->pallet_count)->toBe(24)
        ->and($order->pallet_standard)->toBe('euro_80x120');
});

test('order can store kantar weight fields', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'gross_weight_kg' => 42000.000,
        'tara_weight_kg' => 16000.000,
        'net_weight_kg' => 26000.000,
        'moisture_percent' => 1.5000,
    ]);

    expect((float) $order->gross_weight_kg)->toBe(42000.0)
        ->and((float) $order->net_weight_kg)->toBe(26000.0)
        ->and((float) $order->moisture_percent)->toBe(1.5);
});

test('order can store adr class and temperature control', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'adr_class' => '3',
        'temperature_control' => true,
        'temperature_range' => '+2/+8',
    ]);

    expect($order->adr_class)->toBe('3')
        ->and($order->temperature_control)->toBeTrue()
        ->and($order->temperature_range)->toBe('+2/+8');
});

test('order can link to customer address for loading and delivery', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $loadingAddr = CustomerAddress::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'label' => 'Ana Fabrika',
    ]);
    $deliveryAddr = CustomerAddress::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'label' => 'Müşteri Deposu',
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'loading_address_id' => $loadingAddr->id,
        'delivery_address_id' => $deliveryAddr->id,
    ]);

    expect($order->loadingAddress->label)->toBe('Ana Fabrika')
        ->and($order->deliveryAddress->label)->toBe('Müşteri Deposu');
});

test('another tenant cannot see order on admin orders page', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $userB->givePermissionTo('logistics.admin');

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    Order::factory()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $customerA->id,
        'order_number' => 'CARGO-TENANT-A',
        'cargo_type' => 'bulk',
    ]);

    $this->actingAs($userB)
        ->get(route('admin.orders.index'))
        ->assertSuccessful()
        ->assertDontSee('CARGO-TENANT-A');
});
