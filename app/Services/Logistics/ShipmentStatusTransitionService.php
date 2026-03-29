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
    public function __construct(
        private ShipmentDispatchComplianceGate $dispatchComplianceGate,
    ) {}

    public function markDispatched(Shipment $shipment): void
    {
        if ($shipment->status !== ShipmentStatus::Planned) {
            throw new \InvalidArgumentException(__('Only planned shipments can be dispatched.'));
        }

        $this->dispatchComplianceGate->assertDispatchAllowed($shipment);

        $shipment->update([
            'status' => ShipmentStatus::Dispatched,
            'dispatched_at' => now(),
        ]);

        ShipmentDispatched::dispatch($shipment->fresh());
    }

    /**
     * @param  array{note?: string, received_by?: string, signature_data_url?: string}|null  $pod
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

        $note = isset($pod['note']) ? trim((string) $pod['note']) : '';
        $receivedBy = isset($pod['received_by']) ? trim((string) $pod['received_by']) : '';
        $signatureUrl = isset($pod['signature_data_url']) ? trim((string) $pod['signature_data_url']) : '';

        $signaturePath = null;
        $signedAt = null;
        if ($signatureUrl !== '') {
            $signaturePath = app(PodSignatureStorage::class)->storePngFromDataUrl($shipment, $signatureUrl);
            $signedAt = now()->toIso8601String();
        }

        $hasPodPayload = $note !== '' || $receivedBy !== '' || $signaturePath !== null;

        if ($hasPodPayload) {
            $payload = [
                'note' => $note !== '' ? $note : null,
                'received_by' => $receivedBy !== '' ? $receivedBy : null,
                'recorded_at' => now()->toIso8601String(),
                'recorded_by_user_id' => Auth::id(),
            ];
            if ($signaturePath !== null) {
                $payload['signature_storage_path'] = $signaturePath;
                $payload['signed_at'] = $signedAt;
            }
            $attributes['pod_payload'] = $payload;
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
