<?php

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('mark delivered with png signature stores file and pod payload', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Dispatched,
    ]);

    $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $this->actingAs($user);

    Livewire::test('pages::admin.shipment-show', ['shipment' => $shipment])
        ->set('pod_received_by', 'Depo A')
        ->set('pod_note', 'OK')
        ->set('pod_signature_data', $dataUrl)
        ->call('markDelivered')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->pod_payload['received_by'])->toBe('Depo A')
        ->and($shipment->pod_payload['note'])->toBe('OK')
        ->and($shipment->pod_payload['signature_storage_path'] ?? null)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $shipment->pod_payload['signature_storage_path']);
});

test('authenticated user can download pod signature png', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $path = 'pod-signatures/'.$user->tenant_id.'/'.$shipment->id.'.png';
    $shipment->update([
        'pod_payload' => [
            'signature_storage_path' => $path,
            'received_by' => 'X',
        ],
    ]);

    Storage::disk('local')->put($path, 'x');

    $this->actingAs($user)
        ->get(route('admin.shipments.pod.signature', $shipment->fresh()))
        ->assertSuccessful();
});

test('mark delivered strict ipod with photo upload stores paths', function () {
    Storage::fake('local');
    config(['logistics.ipod.strict' => true]);

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Dispatched,
    ]);

    $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $this->actingAs($user);

    $photo = UploadedFile::fake()->image('delivery.jpg', 40, 40);

    Livewire::test('pages::admin.shipment-show', ['shipment' => $shipment])
        ->set('pod_received_by', 'Depo B')
        ->set('pod_latitude', '36.99')
        ->set('pod_longitude', '35.33')
        ->set('pod_signature_data', $dataUrl)
        ->upload('pod_photo', [$photo])
        ->call('markDelivered')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->pod_payload['photo_storage_path'] ?? null)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $shipment->pod_payload['photo_storage_path']);
});

test('authenticated user can open pod delivery photo', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $path = 'pod-delivery-photos/'.$user->tenant_id.'/'.$shipment->id.'.jpg';
    $shipment->update([
        'pod_payload' => [
            'photo_storage_path' => $path,
            'received_by' => 'X',
        ],
    ]);

    Storage::disk('local')->put($path, 'fake-image');

    $this->actingAs($user)
        ->get(route('admin.shipments.pod.delivery-photo', $shipment->fresh()))
        ->assertSuccessful();
});

test('guest cannot access pod delivery photo route', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $this->get(route('admin.shipments.pod.delivery-photo', $shipment))
        ->assertRedirect(route('login'));
});

test('guest cannot access pod signature route', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $this->get(route('admin.shipments.pod.signature', $shipment))
        ->assertRedirect(route('login'));
});

test('user cannot download signature for other tenant shipment', function () {
    Storage::fake('local');

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $userB->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $userB->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Delivered,
    ]);

    $this->actingAs($userA)
        ->get(route('admin.shipments.pod.signature', $shipment))
        ->assertNotFound();
});
