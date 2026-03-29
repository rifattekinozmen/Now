<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

test('orders index aggregates match tenant scoped row counts', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);

    Order::query()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-TEST-STAT-1',
        'status' => OrderStatus::Draft,
        'ordered_at' => now(),
        'currency_code' => 'TRY',
        'freight_amount' => null,
        'exchange_rate' => null,
        'distance_km' => null,
        'tonnage' => null,
        'incoterms' => null,
        'loading_site' => null,
        'unloading_site' => null,
        'meta' => null,
    ]);

    Order::query()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-TEST-STAT-2',
        'status' => OrderStatus::Confirmed,
        'ordered_at' => now(),
        'currency_code' => 'EUR',
        'freight_amount' => 100.0,
        'exchange_rate' => null,
        'distance_km' => null,
        'tonnage' => null,
        'incoterms' => null,
        'loading_site' => null,
        'unloading_site' => null,
        'meta' => null,
    ]);

    $component = Livewire::actingAs($user)->test('pages::admin.orders-index');

    $stats = $component->instance()->orderIndexStats;

    expect($stats['total'])->toBe(2)
        ->and($stats['draft'])->toBe(1)
        ->and($stats['with_freight'])->toBe(1)
        ->and($stats['currencies'])->toBe(2);
});
