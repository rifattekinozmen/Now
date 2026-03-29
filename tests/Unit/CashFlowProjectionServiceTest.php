<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Finance\CashFlowProjectionService;
use Carbon\Carbon;

test('projects freight rows when due date falls inside window', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'payment_term_days' => 10,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'ordered_at' => Carbon::parse('2026-03-01 12:00:00'),
        'freight_amount' => 123.45,
        'currency_code' => 'TRY',
    ]);

    $svc = new CashFlowProjectionService;
    $rows = $svc->projectForTenant(
        (int) $tenant->id,
        Carbon::parse('2026-03-10')->startOfDay(),
        Carbon::parse('2026-03-15')->endOfDay()
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['order_id'])->toBe($order->id)
        ->and($rows[0]['due_date'])->toBe('2026-03-11')
        ->and($rows[0]['amount'])->toBe('123.45')
        ->and($rows[0]['currency_code'])->toBe('TRY');
});

test('excludes orders when due date is outside window', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'payment_term_days' => 30,
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'ordered_at' => Carbon::parse('2026-01-01'),
        'freight_amount' => 50,
        'currency_code' => 'TRY',
    ]);

    $svc = new CashFlowProjectionService;
    $rows = $svc->projectForTenant(
        (int) $tenant->id,
        Carbon::parse('2026-03-01')->startOfDay(),
        Carbon::parse('2026-03-10')->endOfDay()
    );

    expect($rows)->toHaveCount(0);
});
