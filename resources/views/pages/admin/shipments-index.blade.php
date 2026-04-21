<?php

use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Shipments')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public string $order_id = '';

    public string $vehicle_id = '';

    public string $driver_employee_id = '';

    public string $filterSearch = '';

    public string $filterStatus = '';

    public string $filterVehicle = '';

    public string $filterDriver = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    public bool $shipmentFormOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Shipment::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterVehicle(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterDriver(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
    }

    /**
     * @return array{total: int, planned: int, dispatched: int, delivered: int}
     */
    #[Computed]
    public function shipmentIndexStats(): array
    {
        $planned = ShipmentStatus::Planned->value;
        $dispatched = ShipmentStatus::Dispatched->value;
        $delivered = ShipmentStatus::Delivered->value;

        $row = Shipment::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as planned, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dispatched, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered',
                [$planned, $dispatched, $delivered]
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'planned' => (int) ($row->planned ?? 0),
            'dispatched' => (int) ($row->dispatched ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
        ];
    }

    #[Computed]
    public function activeShipmentAdvancedFilterCount(): int
    {
        $n = 0;
        if ($this->filterStatus !== '') {
            $n++;
        }
        if ($this->filterVehicle !== '') {
            $n++;
        }
        if ($this->filterDriver !== '') {
            $n++;
        }

        return $n;
    }

    public function clearShipmentAdvancedFilters(): void
    {
        $this->filterStatus = '';
        $this->filterVehicle = '';
        $this->filterDriver = '';
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function toggleShipmentForm(): void
    {
        $this->ensureLogisticsAdmin();

        Gate::authorize('create', Shipment::class);

        if ($this->shipmentFormOpen) {
            $this->shipmentFormOpen = false;

            return;
        }

        $this->shipmentFormOpen = true;
    }

    public function shipmentStatusLabel(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Planned => __('Planned'),
            ShipmentStatus::Dispatched => __('Dispatched'),
            ShipmentStatus::Delivered => __('Delivered'),
            ShipmentStatus::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return Builder<Shipment>
     */
    private function shipmentsQuery(): Builder
    {
        $q = Shipment::query()->with(['order', 'vehicle', 'driver']);

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', (int) $this->filterVehicle);
        }

        if ($this->filterDriver !== '') {
            $q->where('driver_employee_id', (int) $this->filterDriver);
        }

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->whereHas('order', function (Builder $oq) use ($term): void {
                    $oq->where('order_number', 'like', $term);
                })
                    ->orWhereHas('vehicle', function (Builder $vq) use ($term): void {
                        $vq->where('plate', 'like', $term);
                    });
            });
        }

        $allowed = ['id', 'status', 'dispatched_at', 'delivered_at', 'created_at', 'order_number', 'plate'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if ($column === 'order_number') {
            $q->leftJoin('orders', 'orders.id', '=', 'shipments.order_id')
                ->select('shipments.*')
                ->orderBy('orders.order_number', $direction);

            return $q;
        }

        if ($column === 'plate') {
            $q->leftJoin('vehicles', 'vehicles.id', '=', 'shipments.vehicle_id')
                ->select('shipments.*')
                ->orderBy('vehicles.plate', $direction);

            return $q;
        }

        return $q->orderBy('shipments.'.$column, $direction);
    }

    #[Computed]
    public function paginatedShipments(): LengthAwarePaginator
    {
        return $this->shipmentsQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'status', 'dispatched_at', 'delivered_at', 'created_at', 'order_number', 'plate'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->selectedIds = [];
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedShipments->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedShipments->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }

        $this->selectedIds = array_values(array_map('intval', $this->selectedIds));
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsAdmin();

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $shipments = Shipment::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($shipments as $shipment) {
            Gate::authorize('delete', $shipment);
            $shipment->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    /**
     * @return array<int, array{id: int, order_number: string, legal_name: string}>
     */
    public function orderOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 0;

        return Cache::remember("order-options.{$tenantId}", 120, function () {
            return Order::query()
                ->with('customer:id,legal_name')
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'legal_name' => $o->customer?->legal_name ?? '—',
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array{id: int, plate: string}>
     */
    public function vehicleOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 0;

        return Cache::remember("vehicle-options.{$tenantId}", 300, function () {
            return Vehicle::query()
                ->orderBy('plate')
                ->limit(500)
                ->get()
                ->map(fn (Vehicle $v) => ['id' => $v->id, 'plate' => $v->plate])
                ->all();
        });
    }

    public function driverOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 0;

        return Cache::remember("driver-options.{$tenantId}", 300, function () {
            return Employee::query()
                ->where('is_driver', true)
                ->orderBy('last_name')
                ->limit(500)
                ->get()
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->fullName()])
                ->all();
        });
    }

    public function saveShipment(): void
    {
        $this->ensureLogisticsAdmin();

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
            'driver_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        Shipment::query()->create([
            'order_id'           => (int) $validated['order_id'],
            'vehicle_id'         => ($validated['vehicle_id'] ?? '') !== '' ? (int) $validated['vehicle_id'] : null,
            'driver_employee_id' => ($validated['driver_employee_id'] ?? '') !== '' ? (int) $validated['driver_employee_id'] : null,
            'status'             => ShipmentStatus::Planned,
        ]);

        $this->reset('order_id', 'vehicle_id', 'driver_employee_id');
        $this->shipmentFormOpen = false;
    }

    public function markDispatched(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsAdmin();

        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->markDispatched($shipment);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markDelivered(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsAdmin();

        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->markDelivered($shipment);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelShipment(int $id, ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsAdmin();

        $shipment = Shipment::query()->findOrFail($id);
        Gate::authorize('update', $shipment);

        try {
            $transitions->cancel($shipment);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteShipments =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::SHIPMENTS_WRITE);
    @endphp
    <x-admin.page-header
        :heading="__('Shipments')"
        :description="__('Dispatch, POD, QR tracking, and status transitions for operations.')"
    >
        <x-slot name="actions">
            <x-admin.index-actions>
                <x-slot name="back">
                    <flux:button :href="route('admin.orders.index')" variant="ghost" wire:navigate>{{ __('Orders') }}</flux:button>
                </x-slot>
                @if ($canWriteShipments)
                    <x-slot name="primary">
                        <flux:button size="sm" icon="plus" variant="primary" wire:click="toggleShipmentForm">
                            {{ __('New shipment') }}
                        </flux:button>
                    </x-slot>
                @endif
            </x-admin.index-actions>
        </x-slot>
    </x-admin.page-header>

    @if (session()->has('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total shipments') }}</flux:text>
            <flux:heading size="xl">{{ $this->shipmentIndexStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Planned') }}</flux:text>
            <flux:heading size="xl">{{ $this->shipmentIndexStats['planned'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Dispatched') }}</flux:text>
            <flux:heading size="xl">{{ $this->shipmentIndexStats['dispatched'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivered') }}</flux:text>
            <flux:heading size="xl">{{ $this->shipmentIndexStats['delivered'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:input
                wire:model.live.debounce.400ms="filterSearch"
                :placeholder="__('Search (shipment id, order no, plate)')"
                icon="magnifying-glass"
                class="max-w-full min-w-0 flex-1 sm:max-w-md"
            />
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($this->activeShipmentAdvancedFilterCount > 0)
                    <flux:button type="button" variant="ghost" size="sm" wire:click="clearShipmentAdvancedFilters">
                        {{ __('Clear filters') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}" class="inline-flex items-center gap-2">
                    {{ __('Filters') }}
                    @if ($this->activeShipmentAdvancedFilterCount > 0)
                        <flux:badge color="zinc" size="sm">{{ $this->activeShipmentAdvancedFilterCount }}</flux:badge>
                    @endif
                </flux:button>
            </div>
        </div>
        @if ($filtersOpen)
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="filterStatus" :label="__('Filter by shipment status')">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\ShipmentStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $this->shipmentStatusLabel($case) }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterVehicle" :label="__('Filter by vehicle')">
                    <option value="">{{ __('All vehicles') }}</option>
                    @foreach ($this->vehicleOptions() as $v)
                        <option value="{{ $v['id'] }}">{{ $v['plate'] }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterDriver" :label="__('Filter by driver')">
                    <option value="">{{ __('All drivers') }}</option>
                    @foreach ($this->driverOptions() as $d)
                        <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </flux:card>

    @if ($canWriteShipments && $shipmentFormOpen)
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('New shipment') }}</flux:heading>
            <form wire:submit="saveShipment" class="flex max-w-xl flex-col gap-4">
                <flux:select wire:model="order_id" :label="__('Order')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($this->orderOptions() as $o)
                        <option value="{{ $o['id'] }}">{{ $o['order_number'] }} — {{ $o['legal_name'] }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="vehicle_id" :label="__('Vehicle (optional)')">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($this->vehicleOptions() as $v)
                        <option value="{{ $v['id'] }}">{{ $v['plate'] }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="driver_employee_id" :label="__('Driver (optional)')">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($this->driverOptions() as $d)
                        <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                    @endforeach
                </flux:select>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('shipmentFormOpen', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save shipment') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($canWriteShipments)
        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="bulkDeleteSelected"
                    wire:confirm="{{ __('Delete selected shipments?') }}"
                >
                    {{ __('Delete selected') }}
                </flux:button>
            </div>
        @endif
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Recent shipments') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWriteShipments)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click.prevent="toggleSelectPage"
                            wire:key="select-page-shipments"
                        />
                    </flux:table.column>
                @endif
                <flux:table.column>
                    <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('ID') }}
                        @if ($sortColumn === 'id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('order_number')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Order') }}
                        @if ($sortColumn === 'order_number')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('plate')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Vehicle') }}
                        @if ($sortColumn === 'plate')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('status')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Status') }}
                        @if ($sortColumn === 'status')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Driver') }}</flux:table.column>
                <flux:table.column>{{ __('Timeline') }}</flux:table.column>
                <flux:table.column>{{ __('Lifecycle') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedShipments as $shipment)
                    <flux:table.row :key="$shipment->id">
                        @if ($canWriteShipments)
                            <flux:table.cell>
                                <flux:checkbox
                                    wire:key="shipment-select-{{ $shipment->id }}"
                                    wire:model.live="selectedIds"
                                    :value="(int) $shipment->id"
                                />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <a
                                href="{{ route('admin.shipments.show', $shipment) }}"
                                wire:navigate
                                class="font-medium text-primary hover:underline"
                            >
                                {{ $shipment->id }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $shipment->order?->order_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $shipment->vehicle?->plate ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $this->shipmentStatusLabel($shipment->status) }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $shipment->driver?->fullName() ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="min-w-[12rem]">
                            @if ($shipment->status === \App\Enums\ShipmentStatus::Cancelled)
                                <flux:badge color="red">{{ __('Cancelled') }}</flux:badge>
                            @else
                                @php
                                    $stepRank = match ($shipment->status) {
                                        \App\Enums\ShipmentStatus::Planned => 0,
                                        \App\Enums\ShipmentStatus::Dispatched => 1,
                                        \App\Enums\ShipmentStatus::Delivered => 2,
                                        default => 0,
                                    };
                                    $labels = [__('Planned'), __('Dispatched'), __('Delivered')];
                                @endphp
                                <div class="flex items-center gap-1" role="list" aria-label="{{ __('Shipment progress') }}">
                                    @foreach ($labels as $i => $label)
                                        @php
                                            $done = $stepRank > $i;
                                            $current = $stepRank === $i;
                                        @endphp
                                        <div class="flex items-center gap-1" role="listitem">
                                            <span
                                                @class([
                                                    'flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1 text-[10px] font-medium',
                                                    'bg-primary text-white' => $done,
                                                    'ring-2 ring-primary ring-offset-2 ring-offset-white dark:ring-offset-zinc-900' => $current && ! $done,
                                                    'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $done && ! $current,
                                                ])
                                                title="{{ $label }}"
                                            >{{ $i + 1 }}</span>
                                            @if ($i < count($labels) - 1)
                                                <span
                                                    @class([
                                                        'h-0.5 w-3 shrink-0 rounded-full',
                                                        'bg-primary' => $stepRank > $i,
                                                        'bg-zinc-200 dark:bg-zinc-600' => $stepRank <= $i,
                                                    ])
                                                ></span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($canWriteShipments)
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
                            @else
                                <flux:text class="text-sm text-zinc-500">{{ __('Read-only — lifecycle changes require an operator.') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteShipments ? 8 : 7 }}">{{ __('No shipments yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedShipments->links() }}
        </div>
    </flux:card>
</div>
