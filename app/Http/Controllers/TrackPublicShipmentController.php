<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class TrackPublicShipmentController extends Controller
{
    public function __invoke(string $token): View|Response
    {
        $shipment = Shipment::query()
            ->withoutGlobalScopes()
            ->where('public_reference_token', $token)
            ->with(['order:id,order_number', 'vehicle:id,plate'])
            ->first();

        if ($shipment === null) {
            return response()->view('track.shipment-not-found', [], 404);
        }

        return view('track.shipment', [
            'shipment' => $shipment,
        ]);
    }
}
