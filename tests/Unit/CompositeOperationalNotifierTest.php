<?php

use App\Contracts\Operations\OperationalNotifier;
use App\Services\Operations\CompositeOperationalNotifier;

afterEach(fn () => Mockery::close());

test('composite notifier forwards to every delegate', function () {
    $first = Mockery::mock(OperationalNotifier::class);
    $second = Mockery::mock(OperationalNotifier::class);

    $first->shouldReceive('notify')->once()->with('demo', ['x' => 1]);
    $second->shouldReceive('notify')->once()->with('demo', ['x' => 1]);

    $composite = new CompositeOperationalNotifier([$first, $second]);
    $composite->notify('demo', ['x' => 1]);
});
