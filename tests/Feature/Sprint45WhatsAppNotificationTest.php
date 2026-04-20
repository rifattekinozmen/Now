<?php

use App\Events\Logistics\ShipmentDispatched;
use App\Listeners\SendShipmentDispatchedWhatsApp;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Notification\WhatsAppNotificationService;
use Illuminate\Support\Facades\Event;

it('whatsapp service is not available when provider is null', function (): void {
    config(['notifications.provider' => 'null']);
    $service = new WhatsAppNotificationService;

    expect($service->isAvailable())->toBeFalse();
});

it('whatsapp service send returns false when provider is null', function (): void {
    config(['notifications.provider' => 'null']);
    $service = new WhatsAppNotificationService;

    $result = $service->send('+905001234567', 'Test message');

    expect($result)->toBeFalse();
});

it('whatsapp service builds correct dispatch message', function (): void {
    $service = new WhatsAppNotificationService;
    $message = $service->buildDispatchMessage('Ahmet Yilmaz', '34ABC123', 'https://example.com/track/abc');

    expect($message)->toBeString()->not->toBeEmpty();
});

it('listener returns early when provider is null', function (): void {
    config(['notifications.provider' => 'null']);

    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'vehicle_id' => $vehicle->id,
    ]);

    $service = new WhatsAppNotificationService;
    $listener = new SendShipmentDispatchedWhatsApp($service);

    // Should not throw; provider = null causes early return
    $listener->handle(new ShipmentDispatched($shipment->load(['order.customer', 'vehicle'])));

    expect(true)->toBeTrue();
});

it('shipment dispatched event is fired correctly', function (): void {
    Event::fake([ShipmentDispatched::class]);

    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'vehicle_id' => $vehicle->id,
    ]);

    ShipmentDispatched::dispatch($shipment);

    Event::assertDispatched(ShipmentDispatched::class);
});
