<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Logistics\PodSignatureStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Print-friendly POD summary (browser Print to PDF; no dompdf dependency).
 */
class ShipmentPodPrintController extends Controller
{
    public function __invoke(Shipment $shipment, PodSignatureStorage $storage): View
    {
        Gate::authorize('view', $shipment);

        $hasSignature = false;
        $path = is_array($shipment->pod_payload)
            ? ($shipment->pod_payload['signature_storage_path'] ?? null)
            : null;

        if (is_string($path) && $path !== '' && $storage->pathBelongsToShipment($shipment, $path)) {
            $hasSignature = $storage->exists($path);
        }

        return view('admin.shipments.pod-print', [
            'shipment' => $shipment->loadMissing(['order.customer', 'vehicle']),
            'hasSignature' => $hasSignature,
        ]);
    }
}
