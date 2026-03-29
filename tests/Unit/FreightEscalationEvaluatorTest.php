<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Operations\FreightEscalationEvaluator;

test('order without freight amount is not flagged', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => null,
    ]);

    $evaluator = app(FreightEscalationEvaluator::class);

    expect($evaluator->orderFreightExceedsReference($order, 1000.0, 0.05))->toBeFalse();
});

test('order freight within threshold is not flagged', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 102.00,
    ]);

    $evaluator = app(FreightEscalationEvaluator::class);

    expect($evaluator->orderFreightExceedsReference($order, 100.0, 0.05))->toBeFalse();
});

test('order freight beyond threshold is flagged', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 120.00,
    ]);

    $evaluator = app(FreightEscalationEvaluator::class);

    expect($evaluator->orderFreightExceedsReference($order, 100.0, 0.05))->toBeTrue();
});
