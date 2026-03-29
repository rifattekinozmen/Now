<?php

namespace App\Events\Logistics;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentDispatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Shipment $shipment
    ) {}
}
