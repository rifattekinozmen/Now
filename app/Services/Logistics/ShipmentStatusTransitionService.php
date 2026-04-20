<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Events\Logistics\ShipmentDispatched;
use App\Jobs\SendUetdsNotificationJob;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;

/**
 * Sevkiyat durum makinesi: Planned → Dispatched → Delivered; iptal (Delivered/Cancelled hariç).
 */
final class ShipmentStatusTransitionService
{
    public function __construct(
        private ShipmentDispatchComplianceGate $dispatchComplianceGate,
        private PodDeliveryComplianceGate $podDeliveryComplianceGate,
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

        $fresh = $shipment->fresh();
        ShipmentDispatched::dispatch($fresh ?? $shipment);

        if (config('logistics.uetds.enabled', false)) {
            SendUetdsNotificationJob::dispatch($shipment->id);
        }
    }

    /**
     * @param  array{note?: string, received_by?: string, signature_data_url?: string}|null  $pod
     */
    public function markDelivered(Shipment $shipment, ?array $pod = null): void
    {
        if ($shipment->status !== ShipmentStatus::Dispatched) {
            throw new \InvalidArgumentException(__('Only dispatched shipments can be marked delivered.'));
        }

        $this->podDeliveryComplianceGate->assertDeliveredProofAllowed($pod);

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

        $hasGeoOrPhoto = $pod !== null && (
            (isset($pod['latitude']) && is_numeric($pod['latitude']))
            || (isset($pod['longitude']) && is_numeric($pod['longitude']))
            || (isset($pod['photo_storage_path']) && is_string($pod['photo_storage_path']) && trim($pod['photo_storage_path']) !== '')
        );

        $hasPodPayload = $note !== '' || $receivedBy !== '' || $signaturePath !== null || $hasGeoOrPhoto;

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
            if ($pod !== null) {
                if (isset($pod['latitude']) && is_numeric($pod['latitude'])) {
                    $payload['delivery_latitude'] = (float) $pod['latitude'];
                }
                if (isset($pod['longitude']) && is_numeric($pod['longitude'])) {
                    $payload['delivery_longitude'] = (float) $pod['longitude'];
                }
                if (isset($pod['photo_storage_path']) && is_string($pod['photo_storage_path'])) {
                    $trim = trim($pod['photo_storage_path']);
                    if ($trim !== '') {
                        $payload['photo_storage_path'] = $trim;
                    }
                }
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
