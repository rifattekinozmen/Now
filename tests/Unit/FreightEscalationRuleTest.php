<?php

use App\Contracts\Operations\OperationalNotifier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Operations\FreightEscalationRule;

afterEach(fn () => Mockery::close());

test('rule notifies when freight exceeds reference threshold', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 200.00,
    ]);

    $notifier = Mockery::mock(OperationalNotifier::class);
    $notifier->shouldReceive('notify')
        ->once()
        ->with('logistics.freight.threshold_exceeded', Mockery::on(function (array $context) use ($order): bool {
            return (int) ($context['order_id'] ?? 0) === $order->id
                && (float) ($context['reference_freight'] ?? 0) === 100.0
                && (float) ($context['actual_freight'] ?? 0) === 200.0;
        }));

    app()->instance(OperationalNotifier::class, $notifier);

    $rule = app(FreightEscalationRule::class);

    expect($rule->checkAndNotify($order, 100.0, 0.05))->toBeTrue();
});

test('rule does not notify when within threshold', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 101.00,
    ]);

    $notifier = Mockery::mock(OperationalNotifier::class);
    $notifier->shouldReceive('notify')->never();

    app()->instance(OperationalNotifier::class, $notifier);

    $rule = app(FreightEscalationRule::class);

    expect($rule->checkAndNotify($order, 100.0, 0.05))->toBeFalse();
});
