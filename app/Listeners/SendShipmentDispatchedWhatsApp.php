<?php

namespace App\Listeners;

use App\Events\Logistics\ShipmentDispatched;
use App\Services\Notification\WhatsAppNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendShipmentDispatchedWhatsApp implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(public readonly WhatsAppNotificationService $whatsApp) {}

    public function handle(ShipmentDispatched $event): void
    {
        // Skip entirely when no provider is configured
        if (config('notifications.provider', 'null') === 'null') {
            return;
        }

        $shipment = $event->shipment;
        $order = $shipment->order;
        $customer = $order?->customer;
        $vehicle = $shipment->vehicle;

        if ($customer === null || $vehicle === null) {
            return;
        }

        $phone = $customer->phone ?? null;
        if (! filled($phone)) {
            Log::debug('SendShipmentDispatchedWhatsApp: customer has no phone', ['customer_id' => $customer->id]);

            return;
        }

        $trackingUrl = route('track.shipment', $shipment->public_reference_token);
        $message = $this->whatsApp->buildDispatchMessage(
            $customer->name,
            $vehicle->plate,
            $trackingUrl
        );

        $this->whatsApp->send($phone, $message, (int) $shipment->tenant_id);
    }
}
