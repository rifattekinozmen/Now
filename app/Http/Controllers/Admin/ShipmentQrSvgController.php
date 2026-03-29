<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\QrCodeSvgService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ShipmentQrSvgController extends Controller
{
    public function __invoke(Shipment $shipment, QrCodeSvgService $qr): Response
    {
        Gate::authorize('view', $shipment);

        $url = route('track.shipment', ['token' => $shipment->public_reference_token]);

        return response($qr->svgForString($url), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
