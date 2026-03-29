<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Events\Logistics\ShipmentDispatched;
use App\Models\Shipment;

/**
 * Sevkiyat durum makinesi: Planned → Dispatched → Delivered; iptal (Delivered/Cancelled hariç).
 */
final class ShipmentStatusTransitionService
{
    public function markDispatched(Shipment $shipment): void
    {
        if ($shipment->status !== ShipmentStatus::Planned) {
            throw new \InvalidArgumentException(__('Only planned shipments can be dispatched.'));
        }

        $shipment->update([
            'status' => ShipmentStatus::Dispatched,
            'dispatched_at' => now(),
        ]);

        ShipmentDispatched::dispatch($shipment->fresh());
    }

    public function markDelivered(Shipment $shipment): void
    {
        if ($shipment->status !== ShipmentStatus::Dispatched) {
            throw new \InvalidArgumentException(__('Only dispatched shipments can be marked delivered.'));
        }

        $shipment->update([
            'status' => ShipmentStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function cancel(Shipment $shipment): void
    {
        if ($shipment->status === ShipmentStatus::Delivered || $shipment->status === ShipmentStatus::Cancelled) {
            throw new \InvalidArgumentException(__('This shipment cannot be cancelled.'));
        }

        $shipment->update([
            'status' => ShipmentStatus::Cancelled,
        ]);
    }
}
