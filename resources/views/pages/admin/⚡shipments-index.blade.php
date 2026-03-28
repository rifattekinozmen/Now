<?php

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipments')] class extends Component
{
    public string $order_id = '';

    public string $vehicle_id = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Shipment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function orderOptions()
    {
        return Order::query()->with('customer')->orderByDesc('id')->limit(200)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    public function vehicleOptions()
    {
        return Vehicle::query()->orderBy('plate')->limit(500)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Shipment>
     */
    public function shipmentList()
    {
        return Shipment::query()->with(['order', 'vehicle'])->orderByDesc('id')->limit(100)->get();
    }

    public function saveShipment(): void
    {
        Gate::authorize('create', Shipment::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where('tenant_id', $tenantId),
            ],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        Shipment::query()->create([
            'order_id' => (int) $validated['order_id'],
            'vehicle_id' => isset($validated['vehicle_id']) && $validated['vehicle_id'] !== ''
                ? (int) $validated['vehicle_id']
                : null,
            'status' => ShipmentStatus::Planned,
        ]);

        $this->reset('order_id', 'vehicle_id');
    }

    public function markDispatched(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->markDispatched($shipment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markDelivered(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->markDelivered($shipment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelShipment(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->cancel($shipment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}; ?>

<x-layouts::app :title="__('Shipments')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <flux:heading size="xl">{{ __('Shipments') }}</flux:heading>

        @if (session()->has('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('New shipment') }}</flux:heading>
            <form wire:submit="saveShipment" class="flex max-w-xl flex-col gap-4">
                <div>
                    <flux:field :label="__('Order')">
                        <select
                            wire:model="order_id"
                            required
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($this->orderOptions() as $o)
                                <option value="{{ $o->id }}">{{ $o->order_number }} — {{ $o->customer?->legal_name ?? '—' }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                </div>

                <div>
                    <flux:field :label="__('Vehicle (optional)')">
                        <select
                            wire:model="vehicle_id"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('—') }}</option>
                            @foreach ($this->vehicleOptions() as $v)
                                <option value="{{ $v->id }}">{{ $v->plate }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                </div>

                <flux:button type="submit" variant="primary">{{ __('Save shipment') }}</flux:button>
            </form>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent shipments') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Lifecycle') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->shipmentList() as $shipment)
                        <flux:table.row :key="$shipment->id">
                            <flux:table.cell>{{ $shipment->order?->order_number ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $shipment->vehicle?->plate ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $shipment->status->value }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-2">
                                    @if ($shipment->status === \App\Enums\ShipmentStatus::Planned)
                                        <flux:button type="button" size="sm" variant="primary" wire:click="markDispatched({{ $shipment->id }})">
                                            {{ __('Dispatch') }}
                                        </flux:button>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="cancelShipment({{ $shipment->id }})" wire:confirm="{{ __('Cancel this shipment?') }}">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    @elseif ($shipment->status === \App\Enums\ShipmentStatus::Dispatched)
                                        <flux:button type="button" size="sm" variant="primary" wire:click="markDelivered({{ $shipment->id }})">
                                            {{ __('Mark delivered') }}
                                        </flux:button>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="cancelShipment({{ $shipment->id }})" wire:confirm="{{ __('Cancel this shipment?') }}">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    @else
                                        <flux:text class="text-sm text-zinc-500">{{ __('No actions') }}</flux:text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell>{{ __('No shipments yet.') }}</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
