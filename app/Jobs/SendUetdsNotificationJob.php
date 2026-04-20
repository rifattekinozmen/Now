<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\Logistics\UetdsNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendUetdsNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $shipmentId) {}

    public function handle(UetdsNotificationService $service): void
    {
        $shipment = Shipment::find($this->shipmentId);

        if ($shipment === null) {
            Log::warning('SendUetdsNotificationJob: shipment not found', ['id' => $this->shipmentId]);

            return;
        }

        $shipment->loadMissing(['order', 'vehicle', 'driver']);

        $service->notify($shipment);
    }
}
