<?php

use App\Contracts\CustomerEngagementNotifier;
use App\Services\Notifications\CompositeCustomerEngagementNotifier;

afterEach(fn () => Mockery::close());

test('composite forwards send to every notifier', function () {
    $a = Mockery::mock(CustomerEngagementNotifier::class);
    $a->shouldReceive('send')->once()->with('sms', 'order_dispatched', ['id' => 1]);

    $b = Mockery::mock(CustomerEngagementNotifier::class);
    $b->shouldReceive('send')->once()->with('sms', 'order_dispatched', ['id' => 1]);

    $composite = new CompositeCustomerEngagementNotifier([$a, $b]);
    $composite->send('sms', 'order_dispatched', ['id' => 1]);
});
