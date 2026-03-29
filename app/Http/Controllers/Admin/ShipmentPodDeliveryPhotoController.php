<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Logistics\PodDeliveryPhotoStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ShipmentPodDeliveryPhotoController extends Controller
{
    public function __invoke(Shipment $shipment, PodDeliveryPhotoStorage $storage): Response
    {
        Gate::authorize('view', $shipment);

        $path = is_array($shipment->pod_payload)
            ? ($shipment->pod_payload['photo_storage_path'] ?? null)
            : null;

        if (! is_string($path) || $path === '' || ! $storage->pathBelongsToShipment($shipment, $path)) {
            abort(404);
        }

        if (! $storage->exists($path)) {
            abort(404);
        }

        return response($storage->get($path), 200, [
            'Content-Type' => $storage->mimeForPath($path),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
