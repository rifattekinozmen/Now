<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;

/**
 * Dokümandaki sipariş yaşam döngüsü adımlarını mevcut Order + Shipment statüleriyle eşler.
 *
 * @phpstan-type Step array{key: string, label: string, done: bool, current: bool}
 */
final class OrderLifecyclePresentation
{
    /**
     * @return array{ cancelled: bool, steps: list<Step> }
     */
    public static function forOrder(Order $order): array
    {
        $order->loadMissing('shipments');

        if ($order->status === OrderStatus::Cancelled) {
            return [
                'cancelled' => true,
                'steps' => self::neutralSteps(),
            ];
        }

        $active = $order->shipments->filter(
            fn ($s) => $s->status !== ShipmentStatus::Cancelled
        );

        $dones = [
            true,
            ! in_array($order->status, [OrderStatus::Draft], true),
            $active->contains(fn ($s) => in_array($s->status, [
                ShipmentStatus::Planned,
                ShipmentStatus::Dispatched,
                ShipmentStatus::Delivered,
            ], true)),
            $active->contains(fn ($s) => in_array($s->status, [
                ShipmentStatus::Dispatched,
                ShipmentStatus::Delivered,
            ], true))
                || in_array($order->status, [OrderStatus::InTransit, OrderStatus::Delivered], true),
            $order->status === OrderStatus::Delivered
                || ($active->isNotEmpty() && $active->every(fn ($s) => $s->status === ShipmentStatus::Delivered)),
        ];

        $firstIncomplete = null;
        foreach ($dones as $i => $done) {
            if (! $done) {
                $firstIncomplete = $i;
                break;
            }
        }

        $keys = ['order_received', 'payment_ok', 'vehicle_planned', 'in_transit', 'delivered'];
        $steps = [];
        foreach ($keys as $i => $key) {
            $steps[] = [
                'key' => $key,
                'label' => self::labelForKey($key),
                'done' => $dones[$i],
                'current' => $firstIncomplete === $i,
            ];
        }

        return ['cancelled' => false, 'steps' => $steps];
    }

    /**
     * @return list<Step>
     */
    private static function neutralSteps(): array
    {
        $keys = ['order_received', 'payment_ok', 'vehicle_planned', 'in_transit', 'delivered'];

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => self::labelForKey($key),
            'done' => false,
            'current' => false,
        ], $keys);
    }

    private static function labelForKey(string $key): string
    {
        return match ($key) {
            'order_received' => __('Order received'),
            'payment_ok' => __('Payment / confirmation'),
            'vehicle_planned' => __('Vehicle planned'),
            'in_transit' => __('In transit'),
            'delivered' => __('Delivered'),
            default => $key,
        };
    }
}
