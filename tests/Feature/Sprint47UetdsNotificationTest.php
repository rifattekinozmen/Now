<?php

use App\Jobs\SendUetdsNotificationJob;
use App\Models\Shipment;
use App\Services\Logistics\UetdsNotificationService;
use Illuminate\Support\Facades\Queue;

it('uetds service returns disabled when feature flag is off', function (): void {
    config(['logistics.uetds.enabled' => false]);
    $service = new UetdsNotificationService;

    expect($service->isEnabled())->toBeFalse();
});

it('uetds service returns failed result when disabled', function (): void {
    config(['logistics.uetds.enabled' => false]);
    $service = new UetdsNotificationService;
    $shipment = Shipment::factory()->create();

    $result = $service->notify($shipment);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('U-ETDS disabled');
});

it('uetds job can be dispatched and queued', function (): void {
    Queue::fake();

    $shipment = Shipment::factory()->create();

    SendUetdsNotificationJob::dispatch($shipment->id);

    Queue::assertPushed(SendUetdsNotificationJob::class, function ($job) use ($shipment): bool {
        return $job->shipmentId === $shipment->id;
    });
});

it('uetds job handles missing shipment gracefully', function (): void {
    $service = new UetdsNotificationService;
    $job = new SendUetdsNotificationJob(999999);

    // Should not throw
    $job->handle($service);

    expect(true)->toBeTrue();
});
