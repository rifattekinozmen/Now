<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\OrderLifecyclePresentation;

test('cancelled order lifecycle is neutral', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    $life = OrderLifecyclePresentation::forOrder($order);

    expect($life['cancelled'])->toBeTrue()
        ->and($life['steps'][0]['done'])->toBeFalse();
});

test('delivered order marks all steps done', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Delivered]);
    Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Delivered,
    ]);

    $life = OrderLifecyclePresentation::forOrder($order->fresh(['shipments']));

    expect($life['cancelled'])->toBeFalse()
        ->and(collect($life['steps'])->every(fn ($s) => $s['done']))->toBeTrue()
        ->and(collect($life['steps'])->contains(fn ($s) => $s['current']))->toBeFalse();
});

test('draft order has payment step as current when first step done', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Draft]);
    $life = OrderLifecyclePresentation::forOrder($order);

    expect($life['steps'][0]['done'])->toBeTrue()
        ->and($life['steps'][1]['done'])->toBeFalse()
        ->and($life['steps'][1]['current'])->toBeTrue();
});
