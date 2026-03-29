<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Events\Logistics\ShipmentDispatched;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;

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

    /**
     * @param  array{note?: string, received_by?: string}|null  $pod
     */
    public function markDelivered(Shipment $shipment, ?array $pod = null): void
    {
        if ($shipment->status !== ShipmentStatus::Dispatched) {
            throw new \InvalidArgumentException(__('Only dispatched shipments can be marked delivered.'));
        }

        $attributes = [
            'status' => ShipmentStatus::Delivered,
            'delivered_at' => now(),
        ];

        if (is_array($pod) && (($pod['note'] ?? '') !== '' || ($pod['received_by'] ?? '') !== '')) {
            $attributes['pod_payload'] = [
                'note' => isset($pod['note']) ? trim((string) $pod['note']) : null,
                'received_by' => isset($pod['received_by']) ? trim((string) $pod['received_by']) : null,
                'recorded_at' => now()->toIso8601String(),
                'recorded_by_user_id' => Auth::id(),
            ];
        }

        $shipment->update($attributes);
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
