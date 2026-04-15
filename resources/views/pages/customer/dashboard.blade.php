<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('My Dashboard')] class extends Component
{
    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{
     *   total_orders: int,
     *   active_orders: int,
     *   delivered_orders: int,
     *   in_transit_shipments: int
     * }
     */
    #[Computed]
    public function kpiStats(): array
    {
        $cid = $this->customerId();

        return [
            'total_orders'        => Order::query()->where('customer_id', $cid)->count(),
            'active_orders'       => Order::query()->where('customer_id', $cid)
                ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::InTransit->value])
                ->count(),
            'delivered_orders'    => Order::query()->where('customer_id', $cid)
                ->where('status', OrderStatus::Delivered->value)
                ->count(),
            'in_transit_shipments' => Shipment::query()
                ->whereHas('order', fn ($q) => $q->where('customer_id', $cid))
                ->where('status', ShipmentStatus::Dispatched->value)
                ->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    #[Computed]
    public function recentOrders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()
            ->where('customer_id', $this->customerId())
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->orderByDesc('ordered_at')
            ->limit(5)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Shipment>
     */
    #[Computed]
    public function activeShipments(): \Illuminate\Database\Eloquent\Collection
    {
        return Shipment::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $this->customerId()))
            ->whereIn('status', [ShipmentStatus::Planned->value, ShipmentStatus::Dispatched->value])
            ->with(['order', 'vehicle'])
            ->orderByDesc('dispatched_at')
            ->limit(5)
            ->get();
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'draft'      => 'zinc',
            'confirmed'  => 'blue',
            'in_transit' => 'amber',
            'delivered'  => 'green',
            'cancelled'  => 'red',
            'planned'    => 'blue',
            'dispatched' => 'amber',
            default      => 'zinc',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft'      => __('Draft'),
            'confirmed'  => __('Confirmed'),
            'in_transit' => __('In transit'),
            'delivered'  => __('Delivered'),
            'cancelled'  => __('Cancelled'),
            'planned'    => __('Planned'),
            'dispatched' => __('In transit'),
            default      => $status,
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div>
        <flux:heading size="xl">{{ __('Welcome back') }}, {{ auth()->user()->name }}!</flux:heading>
        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Here\'s an overview of your orders and shipments.') }}</flux:text>
    </div>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total orders') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_orders'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active orders') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->kpiStats['active_orders'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivered') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['delivered_orders'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('In transit now') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['in_transit_shipments'] > 0 ? 'text-amber-600' : '' }}">
                {{ $this->kpiStats['in_transit_shipments'] }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Recent orders --}}
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Recent orders') }}</flux:heading>
                <flux:button :href="route('customer.orders.index')" variant="ghost" size="sm" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>
            @if ($this->recentOrders->isEmpty())
                <flux:text class="text-sm text-zinc-500">{{ __('No orders yet.') }}</flux:text>
            @else
                <div class="space-y-2">
                    @foreach ($this->recentOrders as $order)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 px-3 py-2 dark:border-zinc-800">
                            <div>
                                <div class="text-sm font-medium">{{ $order->order_number }}</div>
                                <div class="text-xs text-zinc-500">{{ $order->ordered_at?->format('d M Y') }}</div>
                            </div>
                            <flux:badge color="{{ $this->statusColor($order->status instanceof \App\Enums\OrderStatus ? $order->status->value : $order->status) }}" size="sm">
                                {{ $this->statusLabel($order->status instanceof \App\Enums\OrderStatus ? $order->status->value : (string) $order->status) }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Active shipments --}}
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Active shipments') }}</flux:heading>
                <flux:button :href="route('customer.shipments.index')" variant="ghost" size="sm" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>
            @if ($this->activeShipments->isEmpty())
                <flux:text class="text-sm text-zinc-500">{{ __('No active shipments.') }}</flux:text>
            @else
                <div class="space-y-2">
                    @foreach ($this->activeShipments as $shipment)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 px-3 py-2 dark:border-zinc-800">
                            <div>
                                <div class="text-sm font-medium">{{ $shipment->order?->order_number ?? '#'.$shipment->id }}</div>
                                <div class="text-xs text-zinc-500">{{ $shipment->vehicle?->plate ?? '—' }}</div>
                            </div>
                            <flux:badge color="{{ $this->statusColor($shipment->status instanceof \App\Enums\ShipmentStatus ? $shipment->status->value : (string) $shipment->status) }}" size="sm">
                                {{ $this->statusLabel($shipment->status instanceof \App\Enums\ShipmentStatus ? $shipment->status->value : (string) $shipment->status) }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Quick links --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:card class="flex items-center gap-4 p-4 transition-shadow hover:shadow-md">
            <flux:icon.clipboard-document-list class="size-8 text-blue-500" />
            <div class="flex-1">
                <div class="font-semibold">{{ __('My orders') }}</div>
                <div class="text-sm text-zinc-500">{{ __('View and track all your orders') }}</div>
            </div>
            <flux:button :href="route('customer.orders.index')" variant="ghost" size="sm" wire:navigate>
                {{ __('Open') }}
            </flux:button>
        </flux:card>
        <flux:card class="flex items-center gap-4 p-4 transition-shadow hover:shadow-md">
            <flux:icon.cube class="size-8 text-amber-500" />
            <div class="flex-1">
                <div class="font-semibold">{{ __('My shipments') }}</div>
                <div class="text-sm text-zinc-500">{{ __('Track real-time delivery status') }}</div>
            </div>
            <flux:button :href="route('customer.shipments.index')" variant="ghost" size="sm" wire:navigate>
                {{ __('Open') }}
            </flux:button>
        </flux:card>
    </div>
</div>
