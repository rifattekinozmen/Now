<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\Documents\EpodService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateEpodJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $shipmentId) {}

    public function handle(EpodService $service): void
    {
        $shipment = Shipment::find($this->shipmentId);

        if (! $shipment) {
            Log::warning('GenerateEpodJob: shipment not found', ['shipment_id' => $this->shipmentId]);

            return;
        }

        $result = $service->generate($shipment);

        Log::info('GenerateEpodJob: E-POD generated', [
            'shipment_id' => $shipment->id,
            'epod_ready' => $result['epod_ready'],
        ]);
    }
}
