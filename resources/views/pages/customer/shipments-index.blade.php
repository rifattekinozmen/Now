<?php

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Shipments')] class extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{total: int, planned: int, in_transit: int, delivered: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $cid = $this->customerId();

        $base = Shipment::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $cid));

        return [
            'total'      => (clone $base)->count(),
            'planned'    => (clone $base)->where('status', ShipmentStatus::Planned->value)->count(),
            'in_transit' => (clone $base)->where('status', ShipmentStatus::Dispatched->value)->count(),
            'delivered'  => (clone $base)->where('status', ShipmentStatus::Delivered->value)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<Shipment>
     */
    #[Computed]
    public function shipments(): LengthAwarePaginator
    {
        return Shipment::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $this->customerId()))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->with(['order', 'vehicle'])
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    private function statusColor(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Planned   => 'blue',
            ShipmentStatus::Dispatched => 'amber',
            ShipmentStatus::Delivered  => 'green',
            ShipmentStatus::Cancelled  => 'red',
        };
    }

    private function statusLabel(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Planned    => __('Planned'),
            ShipmentStatus::Dispatched => __('In transit'),
            ShipmentStatus::Delivered  => __('Delivered'),
            ShipmentStatus::Cancelled  => __('Cancelled'),
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Shipments') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Track your delivery shipments in real time.') }}</flux:text>
        </div>
        <flux:button :href="route('customer.dashboard')" variant="ghost" size="sm" wire:navigate>
            ← {{ __('Dashboard') }}
        </flux:button>
    </div>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total shipments') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Planned') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->kpiStats['planned'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('In transit') }}</flux:text>
            <flux:heading size="lg" class="text-amber-600">{{ $this->kpiStats['in_transit'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivered') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['delivered'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filter --}}
    <flux:card class="p-4">
        <flux:select wire:model.live="filterStatus" class="w-full sm:w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\ShipmentStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $this->statusLabel($case) }}</option>
            @endforeach
        </flux:select>
    </flux:card>

    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">
        @if ($this->shipments->isEmpty())
            <div class="flex flex-col items-center gap-2 py-16 text-center">
                <flux:icon name="cube" class="size-10 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500">{{ __('No shipments found.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium">{{ __('Order') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Vehicle') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Dispatched') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Delivered') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Track') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('E-POD') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->shipments as $shipment)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $shipment->order?->order_number ?? '#'.$shipment->id }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500">
                                    {{ $shipment->vehicle?->plate ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="{{ $this->statusColor($shipment->status) }}" size="sm">
                                        {{ $this->statusLabel($shipment->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-500">
                                    {{ $shipment->dispatched_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500">
                                    {{ $shipment->delivered_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($shipment->public_reference_token)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            :href="route('track.shipment', $shipment->public_reference_token)"
                                            target="_blank"
                                            icon="arrow-top-right-on-square"
                                        >
                                            {{ __('Track') }}
                                        </flux:button>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (isset($shipment->meta['epod']['generated_at']))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            :href="route('admin.shipments.pod.print', $shipment)"
                                            target="_blank"
                                            icon="document-arrow-down"
                                        >
                                            {{ __('E-POD') }}
                                        </flux:button>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->shipments->links() }}
            </div>
        @endif
    </flux:card>
</div>
