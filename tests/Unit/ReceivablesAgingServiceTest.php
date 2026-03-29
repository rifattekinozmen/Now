<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Finance\ReceivablesAgingService;
use Illuminate\Support\Carbon;

test('receivables aging places overdue order in correct bucket by payment term', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'payment_term_days' => 0,
    ]);
    $asOf = Carbon::parse('2026-03-29')->startOfDay();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivered,
        'ordered_at' => $asOf->copy()->subDays(40),
        'freight_amount' => 1000,
        'currency_code' => 'TRY',
    ]);

    $svc = app(ReceivablesAgingService::class);
    $summary = $svc->summarizeForTenant((int) $tenant->id, $asOf);

    expect($summary['by_currency']['TRY']['days_31_60']['count'])->toBe(1)
        ->and($summary['by_currency']['TRY']['days_31_60']['amount'])->toBe(1000.0)
        ->and($summary['by_currency']['TRY']['current']['count'])->toBe(0);
});

test('receivables aging excludes cancelled orders', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'payment_term_days' => 0,
    ]);
    $asOf = Carbon::parse('2026-03-29')->startOfDay();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Cancelled,
        'ordered_at' => $asOf->copy()->subDays(100),
        'freight_amount' => 500,
        'currency_code' => 'TRY',
    ]);

    $svc = app(ReceivablesAgingService::class);
    $summary = $svc->summarizeForTenant((int) $tenant->id, $asOf);

    expect($summary['by_currency'])->toBeEmpty();
});

test('customer overdue summary aggregates by customer and currency', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'legal_name' => 'Acme Lojistik',
        'payment_term_days' => 0,
    ]);
    $asOf = Carbon::parse('2026-03-29')->startOfDay();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed,
        'ordered_at' => $asOf->copy()->subDays(10),
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed,
        'ordered_at' => $asOf->copy()->subDays(5),
        'freight_amount' => 200,
        'currency_code' => 'TRY',
    ]);

    $svc = app(ReceivablesAgingService::class);
    $summary = $svc->summarizeForTenant((int) $tenant->id, $asOf);

    expect($summary['customer_overdue'])->toHaveCount(1)
        ->and($summary['customer_overdue'][0]['customer_name'])->toBe('Acme Lojistik')
        ->and($summary['customer_overdue'][0]['overdue_amount'])->toBe(300.0)
        ->and($summary['customer_overdue'][0]['max_overdue_days'])->toBe(10);
});
