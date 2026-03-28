<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentStatusTransitionService;

test('planned shipment can be dispatched then delivered', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = new ShipmentStatusTransitionService;

    $svc->markDispatched($shipment);
    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Dispatched)
        ->and($shipment->dispatched_at)->not->toBeNull();

    $svc->markDelivered($shipment);
    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull();
});

test('cannot dispatch non planned shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Dispatched]);
    $svc = new ShipmentStatusTransitionService;

    $svc->markDispatched($shipment);
})->throws(InvalidArgumentException::class);

test('planned shipment can be cancelled', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned]);
    $svc = new ShipmentStatusTransitionService;

    $svc->cancel($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Cancelled);
});

test('cannot cancel delivered shipment', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Delivered]);
    $svc = new ShipmentStatusTransitionService;

    $svc->cancel($shipment);
})->throws(InvalidArgumentException::class);
