<?php

use App\Authorization\LogisticsPermission;
use App\Models\Customer;
use App\Models\PricingCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Pricing Conditions')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public ?int $customer_id = null;
    public string $name = '';
    public string $contract_no = '';
    public string $material_code = '';
    public string $route_from = '';
    public string $route_to = '';
    public string $distance_km = '0';
    public string $base_price = '0';
    public string $currency_code = 'TRY';
    public string $price_per_ton = '0';
    public string $min_tonnage = '0';
    public string $valid_from = '';
    public string $valid_until = '';
    public bool $is_active = true;
    public string $notes = '';

    public string $filterSearch = '';
    public string $filterCustomer = '';
    public string $filterMaterial = '';
    public string $filterStatus = '';

    public string $sortColumn = 'name';
    public string $sortDirection = 'asc';

    public bool $filtersOpen = false;

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingId = null;
    public string $confirmingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', PricingCondition::class);
        $this->valid_from = now()->toDateString();
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterCustomer(): void { $this->resetPage(); }
    public function updatedFilterMaterial(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'name', 'price_per_ton', 'distance_km', 'valid_from', 'valid_until', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->selectedIds = [];
        $this->resetPage();
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedConditions->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedConditions->pluck('id')->toArray();

        return count($pageIds) > 0
            && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function confirmAction(int $id, string $action): void
    {
        $this->confirmingId = $id;
        $this->confirmingAction = $action;
        $this->modal('confirm-action')->show();
    }

    public function executeAction(): void
    {
        if ($this->confirmingAction === 'bulk-delete') {
            $this->bulkDelete();
        } elseif ($this->confirmingId) {
            $this->delete($this->confirmingId);
        }
        $this->confirmingId = null;
        $this->confirmingAction = '';
    }

    public function bulkDelete(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! $authUser->can(\App\Authorization\LogisticsPermission::ADMIN)) {
            abort(403);
        }
        PricingCondition::query()
            ->whereIn('id', $this->selectedIds)
            ->where('tenant_id', $authUser->tenant_id)
            ->delete();
        $this->selectedIds = [];
        $this->resetPage();
    }

    /**
     * @return array{total: int, active: int, expiring_soon: int, avg_price_per_ton: float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $all = PricingCondition::query()->get();

        return [
            'total'              => $all->count(),
            'active'             => $all->where('is_active', true)->count(),
            'expiring_soon'      => PricingCondition::query()->expiringSoon(30)->count(),
            'avg_price_per_ton'  => round((float) $all->where('is_active', true)->avg('price_per_ton'), 2),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Customer> */
    #[Computed]
    public function customers(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->orderBy('legal_name')->get(['id', 'legal_name']);
    }

    private function conditionsQuery(): Builder
    {
        $q = PricingCondition::query()->with('customer');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(fn (Builder $qq) =>
                $qq->where('name', 'like', $term)
                   ->orWhere('contract_no', 'like', $term)
                   ->orWhere('route_from', 'like', $term)
                   ->orWhere('route_to', 'like', $term)
            );
        }

        if ($this->filterCustomer !== '') {
            $q->where('customer_id', $this->filterCustomer);
        }

        if ($this->filterMaterial !== '') {
            $q->where('material_code', $this->filterMaterial);
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection);
    }

    #[Computed]
    public function paginatedConditions(): LengthAwarePaginator
    {
        return $this->conditionsQuery()->paginate(15);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', PricingCondition::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $row = PricingCondition::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId      = $row->id;
        $this->customer_id    = $row->customer_id;
        $this->name           = $row->name;
        $this->contract_no    = $row->contract_no ?? '';
        $this->material_code  = $row->material_code ?? '';
        $this->route_from     = $row->route_from;
        $this->route_to       = $row->route_to;
        $this->distance_km    = (string) $row->distance_km;
        $this->base_price     = (string) $row->base_price;
        $this->currency_code  = $row->currency_code;
        $this->price_per_ton  = (string) $row->price_per_ton;
        $this->min_tonnage    = (string) $row->min_tonnage;
        $this->valid_from     = $row->valid_from->toDateString();
        $this->valid_until    = $row->valid_until?->toDateString() ?? '';
        $this->is_active      = $row->is_active;
        $this->notes          = $row->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::PRICING_CONDITIONS_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'customer_id'   => ['required', 'integer', 'exists:customers,id'],
            'name'          => ['required', 'string', 'max:160'],
            'contract_no'   => ['nullable', 'string', 'max:64'],
            'material_code' => ['nullable', 'string', 'max:32'],
            'route_from'    => ['required', 'string', 'max:100'],
            'route_to'      => ['required', 'string', 'max:100'],
            'distance_km'   => ['required', 'numeric', 'min:0'],
            'base_price'    => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'in:TRY,USD,EUR,GBP'],
            'price_per_ton' => ['required', 'numeric', 'min:0'],
            'min_tonnage'   => ['required', 'numeric', 'min:0'],
            'valid_from'    => ['required', 'date'],
            'valid_until'   => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'     => ['boolean'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'customer_id'   => $validated['customer_id'],
            'name'          => $validated['name'],
            'contract_no'   => filled($validated['contract_no']) ? $validated['contract_no'] : null,
            'material_code' => filled($validated['material_code']) ? $validated['material_code'] : null,
            'route_from'    => $validated['route_from'],
            'route_to'      => $validated['route_to'],
            'distance_km'   => $validated['distance_km'],
            'base_price'    => $validated['base_price'],
            'currency_code' => $validated['currency_code'],
            'price_per_ton' => $validated['price_per_ton'],
            'min_tonnage'   => $validated['min_tonnage'],
            'valid_from'    => $validated['valid_from'],
            'valid_until'   => filled($validated['valid_until']) ? $validated['valid_until'] : null,
            'is_active'     => $validated['is_active'],
            'notes'         => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = PricingCondition::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', PricingCondition::class);
            PricingCondition::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $row = PricingCondition::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $row->update(['is_active' => ! $row->is_active]);
    }

    public function delete(int $id): void
    {
        $row = PricingCondition::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->customer_id   = null;
        $this->name          = '';
        $this->contract_no   = '';
        $this->material_code = '';
        $this->route_from    = '';
        $this->route_to      = '';
        $this->distance_km   = '0';
        $this->base_price    = '0';
        $this->currency_code = 'TRY';
        $this->price_per_ton = '0';
        $this->min_tonnage   = '0';
        $this->valid_from    = now()->toDateString();
        $this->valid_until   = '';
        $this->is_active     = true;
        $this->notes         = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::PRICING_CONDITIONS_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Pricing conditions')"
        :description="__('Freight pricing agreements per customer and route.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New condition') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expiring (30d)') }}</flux:text>
            <flux:heading size="lg" @class(['text-amber-600' => $this->kpiStats['expiring_soon'] > 0])>
                {{ $this->kpiStats['expiring_soon'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg price/ton') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['avg_price_per_ton'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="flex flex-wrap gap-4">
                <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search name / route')" class="max-w-sm" />
                <flux:select wire:model.live="filterCustomer" :label="__('Customer')" class="max-w-[200px]">
                    <option value="">{{ __('All customers') }}</option>
                    @foreach ($this->customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[140px]">
                    <option value="">{{ __('All') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
            </div>
        @endif
    </x-admin.filter-bar>

    {{-- Create / Edit Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId === 0 ? __('New pricing condition') : __('Edit pricing condition') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="customer_id" :label="__('Customer')" required class="sm:col-span-2">
                    <option value="">{{ __('— Select customer —') }}</option>
                    @foreach ($this->customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('Contract name')" required />
                <flux:input wire:model="contract_no" :label="__('Contract no')" placeholder="CNT-2024-001" />
                <flux:input wire:model="material_code" :label="__('Material code')" placeholder="CEM-0101-DOK" />
                <flux:select wire:model="currency_code" :label="__('Currency')" required>
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>
                <flux:input wire:model="route_from" :label="__('Route from')" required />
                <flux:input wire:model="route_to" :label="__('Route to')" required />
                <flux:input wire:model="distance_km" type="number" step="0.1" min="0" :label="__('Distance (km)')" />
                <flux:input wire:model="price_per_ton" type="number" step="0.0001" min="0" :label="__('Price per ton')" />
                <flux:input wire:model="base_price" type="number" step="0.01" min="0" :label="__('Base price')" />
                <flux:input wire:model="min_tonnage" type="number" step="0.01" min="0" :label="__('Min tonnage')" />
                <flux:input wire:model="valid_from" type="date" :label="__('Valid from')" required />
                <flux:input wire:model="valid_until" type="date" :label="__('Valid until')" />
                <div class="flex items-end">
                    <flux:checkbox wire:model="is_active" :label="__('Active')" />
                </div>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete toolbar --}}
    @if ($canWrite && count($selectedIds) > 0)
        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 dark:border-red-800 dark:bg-red-950/30">
            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __(':count selected', ['count' => count($selectedIds)]) }}</span>
            <flux:button variant="danger" size="sm" icon="trash" wire:click="confirmAction(0, 'bulk-delete')">
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        @if ($canWrite)
                            <th class="w-8 py-2 pe-2 ps-2">
                                <flux:checkbox
                                    :checked="$this->isPageFullySelected()"
                                    :indeterminate="count($selectedIds) > 0 && ! $this->isPageFullySelected()"
                                    wire:click="toggleSelectPage"
                                />
                            </th>
                        @endif
                        <th class="py-2 pe-4 font-medium">
                            <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Name') }}@if ($sortColumn === 'name') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Customer') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Route') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Material') }}</th>
                        <th class="py-2 pe-4 font-medium text-end">
                            <button wire:click="sortBy('price_per_ton')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Price/ton') }}@if ($sortColumn === 'price_per_ton') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">
                            <button wire:click="sortBy('valid_until')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Valid until') }}@if ($sortColumn === 'valid_until') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Status') }}</th>
                        @if ($canWrite)
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedConditions as $row)
                        @php
                            $expiringSoon = $row->valid_until && $row->valid_until->diffInDays(now()) <= 30 && $row->valid_until->isFuture();
                        @endphp
                        <tr>
                            @if ($canWrite)
                                <td class="py-2 pe-2 ps-2">
                                    <flux:checkbox wire:model.live="selectedIds" :value="(int) $row->id" />
                                </td>
                            @endif
                            <td class="py-2 pe-4 font-medium">
                                {{ $row->name }}
                                @if ($row->contract_no)
                                    <div class="font-mono text-xs text-zinc-400">{{ $row->contract_no }}</div>
                                @endif
                            </td>
                            <td class="py-2 pe-4">{{ $row->customer?->name ?? '—' }}</td>
                            <td class="py-2 pe-4 text-zinc-500">
                                {{ $row->route_from }} → {{ $row->route_to }}
                                <div class="text-xs text-zinc-400">{{ number_format((float) $row->distance_km, 0) }} km</div>
                            </td>
                            <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $row->material_code ?? '—' }}</td>
                            <td class="py-2 pe-4 text-end font-mono">
                                {{ number_format((float) $row->price_per_ton, 2) }}
                                <span class="text-xs text-zinc-400">{{ $row->currency_code }}</span>
                            </td>
                            <td class="py-2 pe-4">
                                @if ($row->valid_until)
                                    <span @class(['font-medium text-amber-600' => $expiringSoon])>
                                        {{ $row->valid_until->format('d.m.Y') }}
                                    </span>
                                @else
                                    <span class="text-zinc-400">{{ __('No expiry') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-4">
                                @if ($row->is_active)
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </td>
                            @if ($canWrite)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $row->id }})">
                                        {{ $row->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="confirmAction({{ $row->id }}, 'delete')">{{ __('Delete') }}</flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-500">
                                {{ __('No pricing conditions yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedConditions->links() }}
        </div>
    </flux:card>

    <flux:modal name="confirm-action" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Confirm deletion') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeAction">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
