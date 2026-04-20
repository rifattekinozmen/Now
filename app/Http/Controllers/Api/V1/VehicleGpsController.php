<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleGpsPosition;
use App\Services\Logistics\GeofenceCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * GPS position receiver for vehicles.
 *
 * POST /api/v1/vehicles/{vehicle}/gps
 * Requires Sanctum token auth.
 */
class VehicleGpsController extends Controller
{
    public function store(Request $request, Vehicle $vehicle, GeofenceCheckerService $geofence): JsonResponse
    {
        Gate::authorize('update', $vehicle);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $position = VehicleGpsPosition::create([
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'speed' => $data['speed'] ?? null,
            'heading' => $data['heading'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        // Trigger geofence check asynchronously
        $geofence->checkDeliveryArrival($vehicle, (float) $data['lat'], (float) $data['lng']);

        return response()->json(['id' => $position->id, 'recorded_at' => $position->recorded_at], 201);
    }
}
