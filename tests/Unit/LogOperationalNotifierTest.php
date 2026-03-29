<?php

use App\Services\Operations\LogOperationalNotifier;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;

afterEach(fn () => Mockery::close());

test('log operational notifier writes structured info', function () {
    $channelLogger = Mockery::mock(LoggerInterface::class);
    $channelLogger->shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'operational_notification'
                && ($context['event'] ?? null) === 'test.event'
                && ($context['foo'] ?? null) === 'bar';
        });

    $logManager = Mockery::mock(LogManager::class);
    $logManager->shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($channelLogger);

    app()->instance('log', $logManager);

    config(['operations.log_channel' => 'single']);

    $notifier = new LogOperationalNotifier;
    $notifier->notify('test.event', ['foo' => 'bar']);
});
