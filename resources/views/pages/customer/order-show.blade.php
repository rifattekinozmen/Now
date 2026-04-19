<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Order detail')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $user = auth()->user();

        if (! $user?->customer_id) {
            abort(403);
        }

        if ($order->customer_id !== (int) $user->customer_id) {
            abort(403);
        }

        $this->order = $order->load(['shipments.vehicle']);
    }

    private function statusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft                => 'zinc',
            OrderStatus::PendingPriceApproval => 'yellow',
            OrderStatus::Confirmed            => 'blue',
            OrderStatus::InTransit            => 'amber',
            OrderStatus::Delivered            => 'green',
            OrderStatus::Cancelled            => 'red',
        };
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft                => __('Draft'),
            OrderStatus::PendingPriceApproval => __('Pending price approval'),
            OrderStatus::Confirmed            => __('Confirmed'),
            OrderStatus::InTransit            => __('In transit'),
            OrderStatus::Delivered            => __('Delivered'),
            OrderStatus::Cancelled            => __('Cancelled'),
        };
    }

    private function shipmentStatusLabel(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Planned    => __('Planned'),
            ShipmentStatus::Dispatched => __('Dispatched'),
            ShipmentStatus::Delivered  => __('Delivered'),
            ShipmentStatus::Cancelled  => __('Cancelled'),
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Order detail') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                {{ $order->order_number }}
                @if ($order->sas_no)
                    — {{ $order->sas_no }}
                @endif
            </flux:text>
        </div>
        <flux:button :href="route('customer.orders.index')" variant="ghost" size="sm" wire:navigate>
            ← {{ __('My orders') }}
        </flux:button>
    </div>

    {{-- Status banner --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center gap-4">
            <flux:badge color="{{ $this->statusColor($order->status) }}" size="lg">
                {{ $this->statusLabel($order->status) }}
            </flux:badge>
            @if ($order->ordered_at)
                <flux:text class="text-sm text-zinc-500">{{ __('Ordered') }}: {{ $order->ordered_at->format('d M Y') }}</flux:text>
            @endif
            @if ($order->due_date)
                <flux:text class="text-sm text-zinc-500">{{ __('Due') }}: {{ $order->due_date->format('d M Y') }}</flux:text>
            @endif
        </div>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Order details --}}
        <flux:card class="p-5">
            <flux:heading size="md" class="mb-4">{{ __('Order information') }}</flux:heading>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500">{{ __('Order number') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">{{ __('SAS / PO') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->sas_no ?? '—' }}</dd>
                </div>
                @if ($order->incoterms)
                    <div>
                        <dt class="text-zinc-500">{{ __('Incoterms') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->incoterms }}</dd>
                    </div>
                @endif
                @if ($order->freight_amount)
                    <div>
                        <dt class="text-zinc-500">{{ __('Freight') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ number_format($order->freight_amount, 2) }} {{ $order->currency_code }}
                        </dd>
                    </div>
                @endif
                @if ($order->distance_km)
                    <div>
                        <dt class="text-zinc-500">{{ __('Distance (km)') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->distance_km }}</dd>
                    </div>
                @endif
                @if ($order->tonnage)
                    <div>
                        <dt class="text-zinc-500">{{ __('Tonnage') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->tonnage }}</dd>
                    </div>
                @endif
                @if ($order->gross_weight_kg)
                    <div>
                        <dt class="text-zinc-500">{{ __('Gross weight (kg)') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->gross_weight_kg }}</dd>
                    </div>
                @endif
                @if ($order->net_weight_kg)
                    <div>
                        <dt class="text-zinc-500">{{ __('Net weight (kg)') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->net_weight_kg }}</dd>
                    </div>
                @endif
                @if ($order->moisture_percent)
                    <div>
                        <dt class="text-zinc-500">{{ __('Moisture (%)') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->moisture_percent }}</dd>
                    </div>
                @endif
            </dl>
        </flux:card>

        {{-- Sites --}}
        <flux:card class="p-5">
            <flux:heading size="md" class="mb-4">{{ __('Loading & unloading') }}</flux:heading>
            <div class="flex flex-col gap-4 text-sm">
                <div>
                    <p class="mb-1 text-zinc-500">{{ __('Loading site') }}</p>
                    <p class="whitespace-pre-wrap font-medium text-zinc-900 dark:text-zinc-100">{{ $order->loading_site ?? '—' }}</p>
                </div>
                <div>
                    <p class="mb-1 text-zinc-500">{{ __('Unloading site') }}</p>
                    <p class="whitespace-pre-wrap font-medium text-zinc-900 dark:text-zinc-100">{{ $order->unloading_site ?? '—' }}</p>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Shipments --}}
    <flux:card class="overflow-hidden p-0">
        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <flux:heading size="md">{{ __('Shipments') }}</flux:heading>
        </div>
        @if ($order->shipments->isEmpty())
            <div class="flex flex-col items-center gap-2 py-12 text-center">
                <flux:icon name="truck" class="size-8 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500">{{ __('No shipments assigned yet.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium">{{ __('ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Vehicle') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Tracking') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($order->shipments as $shipment)
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $shipment->id }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge
                                        color="{{ match ($shipment->status) { \App\Enums\ShipmentStatus::Planned => 'zinc', \App\Enums\ShipmentStatus::Dispatched => 'amber', \App\Enums\ShipmentStatus::Delivered => 'green', \App\Enums\ShipmentStatus::Cancelled => 'red' } }}"
                                        size="sm"
                                    >
                                        {{ $this->shipmentStatusLabel($shipment->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-500">{{ $shipment->vehicle?->plate ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($shipment->public_reference_token)
                                        <flux:link
                                            :href="route('track.shipment', $shipment->public_reference_token)"
                                            target="_blank"
                                        >
                                            {{ __('Track') }}
                                        </flux:link>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
