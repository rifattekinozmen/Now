<?php

use App\Enums\ShipmentStatus;
use App\Jobs\GenerateEpodJob;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Documents\EpodService;
use Illuminate\Support\Facades\Queue;

it('epod service marks shipment meta when generated', function (): void {
    $tenant = Tenant::factory()->create();
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ShipmentStatus::Delivered->value,
        'pod_payload' => [
            'received_by' => 'Ali Veli',
            'signature_storage_path' => 'sigs/test.png',
            'signed_at' => now()->toIso8601String(),
            'delivery_latitude' => 39.93,
            'delivery_longitude' => 32.85,
        ],
    ]);

    $service = new EpodService;
    $result = $service->generate($shipment);

    expect($result['epod_ready'])->toBeTrue();

    $shipment->refresh();
    expect($shipment->meta)->toHaveKey('epod');
    expect($shipment->meta['epod']['has_signature'])->toBeTrue();
    expect($shipment->meta['epod']['has_gps'])->toBeTrue();
});

it('epod service hasEpod returns false before generation', function (): void {
    $tenant = Tenant::factory()->create();
    $shipment = Shipment::factory()->create(['tenant_id' => $tenant->id]);

    $service = new EpodService;
    expect($service->hasEpod($shipment))->toBeFalse();
});

it('epod service hasEpod returns true after generation', function (): void {
    $tenant = Tenant::factory()->create();
    $shipment = Shipment::factory()->create(['tenant_id' => $tenant->id]);

    $service = new EpodService;
    $service->generate($shipment);

    $shipment->refresh();
    expect($service->hasEpod($shipment))->toBeTrue();
});

it('generate epod job is dispatched and queued', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $shipment = Shipment::factory()->create(['tenant_id' => $tenant->id]);

    GenerateEpodJob::dispatch($shipment->id);

    Queue::assertPushed(GenerateEpodJob::class, fn ($job) => $job->shipmentId === $shipment->id);
});

it('generate epod job handles missing shipment gracefully', function (): void {
    $service = new EpodService;
    $job = new GenerateEpodJob(999999);

    // Should not throw
    $job->handle($service);

    expect(true)->toBeTrue();
});
