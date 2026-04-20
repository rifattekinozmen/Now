<?php

namespace App\Services\Documents;

use App\Models\Shipment;

/**
 * Electronic Proof of Delivery (E-POD) service.
 *
 * Generates an E-POD record in shipment.meta when a POD is submitted.
 * The printable PDF view is served via the existing ShipmentPodPrintController
 * (browser "Print → Save as PDF"), which requires no extra dependencies.
 *
 * If a PDF engine (e.g. barryvdh/laravel-dompdf) is installed in future,
 * swap generatePdf() to produce a real PDF file and store the path in meta.
 */
final class EpodService
{
    /**
     * Mark the shipment as having a completed E-POD and record metadata.
     *
     * @return array{epod_ready: bool, print_url: string}
     */
    public function generate(Shipment $shipment): array
    {
        $pod = is_array($shipment->pod_payload) ? $shipment->pod_payload : [];

        $meta = $shipment->meta ?? [];
        $meta['epod'] = [
            'generated_at' => now()->toIso8601String(),
            'received_by' => $pod['received_by'] ?? null,
            'has_signature' => isset($pod['signature_storage_path']),
            'has_gps' => isset($pod['delivery_latitude']),
            'delivery_lat' => $pod['delivery_latitude'] ?? null,
            'delivery_lng' => $pod['delivery_longitude'] ?? null,
            'signed_at' => $pod['signed_at'] ?? null,
        ];
        $shipment->update(['meta' => $meta]);

        return [
            'epod_ready' => true,
            'print_url' => route('admin.shipments.pod.print', $shipment),
        ];
    }

    /**
     * Whether this shipment has a completed E-POD.
     */
    public function hasEpod(Shipment $shipment): bool
    {
        $meta = $shipment->meta ?? [];

        return isset($meta['epod']['generated_at']);
    }

    /**
     * Return the E-POD metadata array, or null if not yet generated.
     *
     * @return array<string, mixed>|null
     */
    public function epodMeta(Shipment $shipment): ?array
    {
        $meta = $shipment->meta ?? [];

        return $meta['epod'] ?? null;
    }
}
