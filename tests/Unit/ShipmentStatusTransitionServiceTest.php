<?php

use App\Contracts\Operations\OperationalNotifier;
use App\Enums\ShipmentStatus;
use App\Events\Logistics\ShipmentDispatched;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Support\Facades\Event;

afterEach(fn () => Mockery::close());

test('planned shipment can be dispatched then delivered', function () {
    Event::fake([ShipmentDispatched::class]);
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = new ShipmentStatusTransitionService;

    $svc->markDispatched($shipment);
    Event::assertDispatched(ShipmentDispatched::class, function (ShipmentDispatched $e) use ($shipment): bool {
        return $e->shipment->id === $shipment->id;
    });
    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Dispatched)
        ->and($shipment->dispatched_at)->not->toBeNull();

    $svc->markDelivered($shipment);
    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull();
});

test('cannot dispatch non planned shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Dispatched]);
    $svc = new ShipmentStatusTransitionService;

    $svc->markDispatched($shipment);
})->throws(InvalidArgumentException::class);

test('mark dispatched triggers operational notification log', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);

    $notifier = Mockery::mock(OperationalNotifier::class);
    $notifier->shouldReceive('notify')
        ->once()
        ->with('logistics.shipment.dispatched', Mockery::on(function (array $context) use ($shipment): bool {
            return (int) ($context['shipment_id'] ?? 0) === $shipment->id
                && array_key_exists('tenant_id', $context)
                && array_key_exists('order_id', $context);
        }));

    app()->instance(OperationalNotifier::class, $notifier);

    $svc = new ShipmentStatusTransitionService;
    $svc->markDispatched($shipment);
});

test('planned shipment can be cancelled', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = new ShipmentStatusTransitionService;

    $svc->cancel($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Cancelled);
});

test('cannot cancel delivered shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Delivered]);
    $svc = new ShipmentStatusTransitionService;

    $svc->cancel($shipment);
})->throws(InvalidArgumentException::class);
