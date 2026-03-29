<?php

use App\Contracts\CustomerEngagementNotifier;
use App\Contracts\Operations\OperationalNotifier;
use App\Events\Logistics\ShipmentDispatched;
use App\Listeners\Operations\NotifyShipmentDispatched;
use App\Models\Shipment;

afterEach(fn () => Mockery::close());

test('notify shipment dispatched forwards to operational and customer engagement notifiers', function () {
    $operational = Mockery::mock(OperationalNotifier::class);
    $operational->shouldReceive('notify')
        ->once()
        ->with('logistics.shipment.dispatched', Mockery::type('array'));

    $engagement = Mockery::mock(CustomerEngagementNotifier::class);
    $engagement->shouldReceive('send')
        ->once()
        ->with(
            'logistics',
            'shipment.dispatched',
            Mockery::on(function (array $ctx): bool {
                return isset($ctx['shipment_id'], $ctx['tenant_id'], $ctx['order_id'], $ctx['public_reference_token']);
            }),
        );

    $shipment = Shipment::factory()->create([
        'public_reference_token' => 'test-token-48chars-long-placeholder-here-now',
    ]);

    $listener = new NotifyShipmentDispatched($operational, $engagement);
    $listener->handle(new ShipmentDispatched($shipment));
});
