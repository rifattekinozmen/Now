<?php

use App\Contracts\Operations\OperationalNotifier;
use App\Enums\ShipmentStatus;
use App\Events\Logistics\ShipmentDispatched;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Mockery::close());

test('planned shipment can be dispatched then delivered', function () {
    Event::fake([ShipmentDispatched::class]);
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = app(ShipmentStatusTransitionService::class);

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
        ->and($shipment->delivered_at)->not->toBeNull()
        ->and($shipment->pod_payload)->toBeNull();
});

test('mark delivered rejects invalid signature data url', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Dispatched,
    ]);
    $svc = app(ShipmentStatusTransitionService::class);

    $svc->markDelivered($shipment, ['signature_data_url' => 'data:text/plain;base64,QQ==']);
})->throws(InvalidArgumentException::class);

test('mark delivered stores pod when user authenticated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Dispatched,
    ]);
    $svc = app(ShipmentStatusTransitionService::class);

    $svc->markDelivered($shipment, ['note' => 'Tamam', 'received_by' => 'Depo']);

    $shipment->refresh();
    expect($shipment->pod_payload['note'])->toBe('Tamam')
        ->and($shipment->pod_payload['received_by'])->toBe('Depo')
        ->and($shipment->pod_payload['recorded_by_user_id'])->toBe($user->id);
});

test('cannot dispatch when vehicle inspection expired', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $vehicle = Vehicle::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'inspection_valid_until' => now()->subMonth(),
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $customer->tenant_id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $svc = app(ShipmentStatusTransitionService::class);

    $svc->markDispatched($shipment);
})->throws(InvalidArgumentException::class);

test('cannot dispatch non planned shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Dispatched]);
    $svc = app(ShipmentStatusTransitionService::class);

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

    $svc = app(ShipmentStatusTransitionService::class);
    $svc->markDispatched($shipment);
});

test('planned shipment can be cancelled', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = app(ShipmentStatusTransitionService::class);

    $svc->cancel($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Cancelled);
});

test('cannot cancel delivered shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Delivered]);
    $svc = app(ShipmentStatusTransitionService::class);

    $svc->cancel($shipment);
})->throws(InvalidArgumentException::class);

test('mark delivered rejects strict ipod without gps and photo', function () {
    config(['logistics.ipod.strict' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Dispatched,
    ]);
    $svc = app(ShipmentStatusTransitionService::class);

    $svc->markDelivered($shipment, [
        'signature_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    ]);
})->throws(InvalidArgumentException::class);
