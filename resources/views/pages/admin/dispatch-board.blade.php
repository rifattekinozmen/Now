<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Dispatch Board')] class extends Component
{
    use RequiresLogisticsAdmin;

    public string $filterSearch = '';

    /** @var array<int, array{vehicle_id: int, driver_id: int|null}> */
    public array $vehicleDriverMap = [];

    public ?string $toast = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function pendingOrders(): Collection
    {
        $q = Order::query()
            ->with('customer')
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Draft->value])
            ->whereDoesntHave('shipments', fn ($sq) => $sq->whereIn('status', [
                ShipmentStatus::Planned->value,
                ShipmentStatus::Dispatched->value,
            ]));

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function ($qq) use ($term): void {
                $qq->where('order_number', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        return $q->orderByDesc('ordered_at')->limit(50)->get();
    }

    /**
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function availableVehicles(): Collection
    {
        return Vehicle::query()
            ->whereDoesntHave('shipments', fn ($sq) => $sq->where('status', ShipmentStatus::Dispatched->value))
            ->orderBy('plate')
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function availableDrivers(): Collection
    {
        return Employee::query()
            ->where('is_driver', true)
            ->whereDoesntHave('drivenShipments', fn ($sq) => $sq->where('status', ShipmentStatus::Dispatched->value))
            ->orderBy('first_name')
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, Shipment>
     */
    #[Computed]
    public function recentShipments(): Collection
    {
        return Shipment::query()
            ->with(['order.customer', 'vehicle', 'driver'])
            ->whereIn('status', [ShipmentStatus::Planned->value, ShipmentStatus::Dispatched->value])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    /**
     * Assign order to vehicle (and optionally driver), create a Planned shipment.
     */
    public function assignOrder(int $orderId, int $vehicleId, ?int $driverId = null): void
    {
        Gate::authorize('create', Shipment::class);

        $order = Order::findOrFail($orderId);
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Prevent duplicate planned shipment for same order
        $existing = Shipment::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [ShipmentStatus::Planned->value, ShipmentStatus::Dispatched->value])
            ->exists();

        if ($existing) {
            $this->toast = __('This order already has an active shipment.');

            return;
        }

        Shipment::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'order_id'           => $orderId,
            'vehicle_id'         => $vehicleId,
            'driver_employee_id' => $driverId,
            'status'             => ShipmentStatus::Planned->value,
        ]);

        // Update order status to in_transit
        $order->update(['status' => OrderStatus::InTransit->value]);

        unset($this->pendingOrders, $this->availableVehicles, $this->availableDrivers, $this->recentShipments);

        $this->toast = __('Shipment planned: Order :no → :plate', [
            'no'    => $order->order_number,
            'plate' => $vehicle->plate,
        ]);
    }

    public function updatedFilterSearch(): void
    {
        unset($this->pendingOrders);
    }

    public function clearToast(): void
    {
        $this->toast = null;
    }
}; ?>

<div
    class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8"
    x-data="{
        draggingOrderId: null,
        draggingOrderNo: '',
        dragStart(orderId, orderNo) {
            this.draggingOrderId = orderId;
            this.draggingOrderNo = orderNo;
        },
        dragEnd() {
            this.draggingOrderId = null;
            this.draggingOrderNo = '';
        },
        dropOnVehicle(vehicleId, driverId) {
            if (!this.draggingOrderId) return;
            $wire.assignOrder(this.draggingOrderId, vehicleId, driverId);
            this.dragEnd();
        }
    }"
>
    <x-admin.page-header :heading="__('Dispatch Board')" :description="__('Drag orders onto vehicles to create shipments.')">
        <x-slot name="actions">
            <flux:input wire:model.live.debounce.300ms="filterSearch" :placeholder="__('Search order / customer…')" class="max-w-xs min-w-0" />
        </x-slot>
    </x-admin.page-header>

    {{-- Toast --}}
    @if ($toast)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => { show = false; $wire.clearToast(); }, 4000)"
            x-show="show"
            x-transition
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-200"
        >
            {{ $toast }}
        </div>
    @endif

    {{-- Board: 3 columns --}}
    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Column 1: Pending Orders --}}
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Pending Orders') }}</flux:heading>
                <flux:badge color="zinc" size="sm">{{ $this->pendingOrders->count() }}</flux:badge>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Drag an order card onto a vehicle to dispatch') }}</p>

            <div class="flex flex-col gap-2 min-h-32">
                @forelse ($this->pendingOrders as $order)
                    <div
                        draggable="true"
                        @dragstart="dragStart({{ $order->id }}, '{{ $order->order_number }}')"
                        @dragend="dragEnd()"
                        class="cursor-grab rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition hover:shadow-md active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                                {{ $order->order_number }}
                            </span>
                            @php $statusColor = match($order->status) {
                                \App\Enums\OrderStatus::Confirmed  => 'blue',
                                \App\Enums\OrderStatus::Draft      => 'zinc',
                                default                            => 'zinc',
                            }; @endphp
                            <flux:badge :color="$statusColor" size="sm">{{ $order->status->value }}</flux:badge>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $order->customer?->name ?? '—' }}</p>
                        @if ($order->loading_site || $order->unloading_site)
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $order->loading_site ?? '?' }} → {{ $order->unloading_site ?? '?' }}
                            </p>
                        @endif
                        @if ($order->tonnage)
                            <p class="mt-0.5 text-xs text-zinc-400">{{ $order->tonnage }} t</p>
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-400 dark:border-zinc-700">
                        {{ __('No pending orders') }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 2: Available Vehicles / Drivers --}}
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Available Vehicles') }}</flux:heading>
                <flux:badge color="green" size="sm">{{ $this->availableVehicles->count() }}</flux:badge>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Drop an order card here to create a shipment') }}</p>

            <div class="flex flex-col gap-2 min-h-32">
                @forelse ($this->availableVehicles as $vehicle)
                    @php
                        $driver = $this->availableDrivers->first();
                    @endphp
                    <div
                        @dragover.prevent
                        @drop.prevent="dropOnVehicle({{ $vehicle->id }}, null)"
                        :class="draggingOrderId ? 'border-blue-400 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 bg-white dark:bg-zinc-800'"
                        class="rounded-lg border-2 p-3 shadow-sm transition dark:border-zinc-700"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <flux:icon name="truck" class="size-4 text-zinc-500" />
                                <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-100">{{ $vehicle->plate }}</span>
                            </div>
                            <flux:badge color="green" size="sm">{{ __('Available') }}</flux:badge>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">{{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->manufacture_year }})</p>

                        {{-- Driver assignment dropdown --}}
                        <div class="mt-2">
                            <select
                                class="w-full rounded border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100"
                                @change="dropOnVehicle({{ $vehicle->id }}, $event.target.value ? parseInt($event.target.value) : null)"
                                :disabled="!draggingOrderId"
                            >
                                <option value="">{{ __('No driver') }}</option>
                                @foreach ($this->availableDrivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->first_name }} {{ $driver->last_name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-0.5 text-[10px] text-zinc-400">{{ __('Drop order, then assign driver') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-400 dark:border-zinc-700">
                        {{ __('No available vehicles') }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 3: Planned / Dispatched --}}
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Planned / Dispatched') }}</flux:heading>
                <flux:badge color="blue" size="sm">{{ $this->recentShipments->count() }}</flux:badge>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Active shipments') }}</p>

            <div class="flex flex-col gap-2 min-h-32">
                @forelse ($this->recentShipments as $shipment)
                    <div class="rounded-lg border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('admin.shipments.show', $shipment) }}" wire:navigate
                               class="font-mono text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                {{ $shipment->order?->order_number ?? '#'.$shipment->id }}
                            </a>
                            @php $sc = match($shipment->status) {
                                \App\Enums\ShipmentStatus::Dispatched => 'green',
                                \App\Enums\ShipmentStatus::Planned    => 'blue',
                                default                               => 'zinc',
                            }; @endphp
                            <flux:badge :color="$sc" size="sm">{{ $shipment->status->value }}</flux:badge>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $shipment->order?->customer?->name ?? '—' }}
                        </p>
                        <div class="mt-1 flex items-center gap-2 text-xs text-zinc-400">
                            @if ($shipment->vehicle)
                                <span class="flex items-center gap-1">
                                    <flux:icon name="truck" class="size-3" />
                                    {{ $shipment->vehicle->plate }}
                                </span>
                            @endif
                            @if ($shipment->driver)
                                <span>· {{ $shipment->driver->first_name }} {{ $shipment->driver->last_name }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-400 dark:border-zinc-700">
                        {{ __('No active shipments') }}
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
