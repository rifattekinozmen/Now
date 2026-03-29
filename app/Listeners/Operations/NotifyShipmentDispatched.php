<?php

namespace App\Listeners\Operations;

use App\Contracts\CustomerEngagementNotifier;
use App\Contracts\Operations\OperationalNotifier;
use App\Events\Logistics\ShipmentDispatched;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyShipmentDispatched implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private OperationalNotifier $notifier,
        private CustomerEngagementNotifier $customerEngagement,
    ) {}

    public function handle(ShipmentDispatched $event): void
    {
        $shipment = $event->shipment;

        $this->notifier->notify('logistics.shipment.dispatched', [
            'shipment_id' => $shipment->id,
            'tenant_id' => $shipment->tenant_id,
            'order_id' => $shipment->order_id,
        ]);

        $this->customerEngagement->send('logistics', 'shipment.dispatched', [
            'shipment_id' => $shipment->id,
            'tenant_id' => $shipment->tenant_id,
            'order_id' => $shipment->order_id,
            'public_reference_token' => $shipment->public_reference_token,
        ]);
    }
}
