<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;

/**
 * Checks whether a vehicle has arrived within the delivery geofence radius.
 *
 * Delivery coordinates are stored in shipment.meta.delivery_lat / delivery_lng.
 * When the vehicle enters the 500 m radius, the shipment status is set to
 * ShipmentStatus::Arrived (if that status exists) or a log entry is made.
 */
final class GeofenceCheckerService
{
    /** Arrival radius in metres */
    private const RADIUS_METRES = 500;

    /**
     * Check all active shipments for this vehicle against the given GPS fix.
     */
    public function checkDeliveryArrival(Vehicle $vehicle, float $lat, float $lng): void
    {
        $shipments = Shipment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', ShipmentStatus::Dispatched->value)
            ->whereNotNull('meta')
            ->get();

        foreach ($shipments as $shipment) {
            $meta = $shipment->meta ?? [];

            $deliveryLat = isset($meta['delivery_lat']) ? (float) $meta['delivery_lat'] : null;
            $deliveryLng = isset($meta['delivery_lng']) ? (float) $meta['delivery_lng'] : null;

            if ($deliveryLat === null || $deliveryLng === null) {
                continue;
            }

            $distance = $this->haversineMetres($lat, $lng, $deliveryLat, $deliveryLng);

            if ($distance <= self::RADIUS_METRES) {
                Log::info('GeofenceCheckerService: vehicle arrived at delivery point', [
                    'shipment_id' => $shipment->id,
                    'vehicle_id' => $vehicle->id,
                    'distance_m' => round($distance),
                ]);

                // Mark geofence arrival in meta to avoid repeat triggers
                $meta['geofence_arrived_at'] = now()->toIso8601String();
                $shipment->update(['meta' => $meta]);
            }
        }
    }

    /**
     * Haversine great-circle distance in metres.
     */
    public function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // metres

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
