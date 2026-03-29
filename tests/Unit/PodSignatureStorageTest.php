<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Logistics\PodSignatureStorage;
use Illuminate\Support\Facades\Storage;

test('storePngFromDataUrl writes png to local disk', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $storage = new PodSignatureStorage;
    $path = $storage->storePngFromDataUrl($shipment, $dataUrl);

    expect($path)->toBe('pod-signatures/'.$tenant->id.'/'.$shipment->id.'.png');
    Storage::disk('local')->assertExists($path);
});

test('storePngFromDataUrl rejects non png data url', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $storage = new PodSignatureStorage;
    $storage->storePngFromDataUrl($shipment, 'data:image/jpeg;base64,AAAA');
})->throws(InvalidArgumentException::class);

test('pathBelongsToShipment validates expected layout', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $storage = new PodSignatureStorage;

    expect($storage->pathBelongsToShipment($shipment, 'pod-signatures/'.$tenant->id.'/'.$shipment->id.'.png'))->toBeTrue()
        ->and($storage->pathBelongsToShipment($shipment, 'pod-signatures/999/'.$shipment->id.'.png'))->toBeFalse()
        ->and($storage->pathBelongsToShipment($shipment, '../evil.png'))->toBeFalse();
});
