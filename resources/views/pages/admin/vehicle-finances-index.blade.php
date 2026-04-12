<?php

use App\Enums\VehicleFinanceType;
use App\Models\Vehicle;
use App\Models\VehicleFinance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Vehicle Finances')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $vehicle_id        = '';
    public string $finance_type      = 'insurance';
    public string $amount            = '';
    public string $currency_code     = 'TRY';
    public string $transaction_date  = '';
    public string $due_date          = '';
    public string $paid_at           = '';
    public string $reference_no      = '';
    public string $description       = '';

    // Filters
    public string $filterVehicle = '';
    public string $filterType    = '';
    public string $filterFrom    = '';
    public string $filterTo      = '';
    public string $filterPaid    = '';

    public string $sortColumn    = 'transaction_date';
    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', VehicleFinance::class);
        $this->transaction_date = now()->format('Y-m-d');
    }

    public function updatedFilterVehicle(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterFrom(): void { $this->resetPage(); }
    public function updatedFilterTo(): void { $this->resetPage(); }
    public function updatedFilterPaid(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'transaction_date', 'finance_type', 'amount', 'paid_at', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedFinances->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedFinances->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('create', VehicleFinance::class);
        $count             = VehicleFinance::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $this->delete($this->confirmingDeleteId);
        }
        $this->confirmingDeleteId = null;
    }

    /**
     * @return array{total:int, unpaid:int, paid_this_month:int, total_amount:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'            => VehicleFinance::query()->count(),
            'unpaid'           => VehicleFinance::query()->whereNull('paid_at')->count(),
            'paid_this_month'  => VehicleFinance::query()
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->count(),
            'total_amount'     => (float) VehicleFinance::query()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()->orderBy('plate')->get();
    }

    private function financeQuery(): Builder
    {
        $q = VehicleFinance::query()->with('vehicle');

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', (int) $this->filterVehicle);
        }
        if ($this->filterType !== '') {
            $q->where('finance_type', $this->filterType);
        }
        if ($this->filterFrom !== '') {
            $q->where('transaction_date', '>=', $this->filterFrom);
        }
        if ($this->filterTo !== '') {
            $q->where('transaction_date', '<=', $this->filterTo);
        }
        if ($this->filterPaid === 'paid') {
            $q->whereNotNull('paid_at');
        } elseif ($this->filterPaid === 'unpaid') {
            $q->whereNull('paid_at');
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedFinances(): LengthAwarePaginator
    {
        return $this->financeQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $vf                    = VehicleFinance::query()->findOrFail($id);
        $this->editingId       = $id;
        $this->vehicle_id      = (string) ($vf->vehicle_id ?? '');
        $this->finance_type    = $vf->finance_type->value;
        $this->amount          = (string) ($vf->amount ?? '');
        $this->currency_code   = $vf->currency_code;
        $this->transaction_date = $vf->transaction_date?->format('Y-m-d') ?? '';
        $this->due_date        = $vf->due_date?->format('Y-m-d') ?? '';
        $this->paid_at         = $vf->paid_at?->format('Y-m-d') ?? '';
        $this->reference_no    = $vf->reference_no ?? '';
        $this->description     = $vf->description ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'vehicle_id'       => ['required', 'integer', 'exists:vehicles,id'],
            'finance_type'     => ['required', 'in:insurance,registration,loan_payment,repair,maintenance,road_tax,other'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'currency_code'    => ['required', 'string', 'size:3'],
            'transaction_date' => ['required', 'date'],
            'due_date'         => ['nullable', 'date'],
            'paid_at'          => ['nullable', 'date'],
            'reference_no'     => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'vehicle_id'       => (int) $validated['vehicle_id'],
            'finance_type'     => $validated['finance_type'],
            'amount'           => (float) $validated['amount'],
            'currency_code'    => $validated['currency_code'],
            'transaction_date' => $validated['transaction_date'],
            'due_date'         => filled($validated['due_date']) ? $validated['due_date'] : null,
            'paid_at'          => filled($validated['paid_at']) ? $validated['paid_at'] : null,
            'reference_no'     => filled($validated['reference_no']) ? $validated['reference_no'] : null,
            'description'      => filled($validated['description']) ? $validated['description'] : null,
        ];

        if ($this->editingId && $this->editingId > 0) {
            Gate::authorize('update', VehicleFinance::query()->findOrFail($this->editingId));
            VehicleFinance::query()->findOrFail($this->editingId)->update($payload);
        } else {
            Gate::authorize('create', VehicleFinance::class);
            VehicleFinance::query()->create($payload);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function markPaid(int $id): void
    {
        $vf = VehicleFinance::query()->findOrFail($id);
        Gate::authorize('update', $vf);
        $vf->update(['paid_at' => now()->format('Y-m-d')]);
    }

    public function delete(int $id): void
    {
        $vf = VehicleFinance::query()->findOrFail($id);
        Gate::authorize('delete', $vf);
        $vf->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id        = '';
        $this->finance_type      = 'insurance';
        $this->amount            = '';
        $this->currency_code     = 'TRY';
        $this->transaction_date  = now()->format('Y-m-d');
        $this->due_date          = '';
        $this->paid_at           = '';
        $this->reference_no      = '';
        $this->description       = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Vehicle Finances')"
        :description="__('Track insurance, registration, loans and other vehicle-related costs.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\VehicleFinance::class)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New entry') }}
                </flux:button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    {{-- Flash --}}
    @if (session('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('bulk_deleted') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total records') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unpaid') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['unpaid'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->kpiStats['unpaid'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paid this month') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['paid_this_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This month total') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_amount'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:text class="font-medium">{{ __('Filters') }}</flux:text>
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="mt-4 flex flex-wrap gap-4">
                <flux:select wire:model.live="filterVehicle" :label="__('Vehicle')" class="max-w-[220px]">
                    <option value="">{{ __('All vehicles') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[200px]">
                    <option value="">{{ __('All types') }}</option>
                    @foreach (\App\Enums\VehicleFinanceType::cases() as $ft)
                        <option value="{{ $ft->value }}">{{ $ft->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterPaid" :label="__('Payment status')" class="max-w-[180px]">
                    <option value="">{{ __('All') }}</option>
                    <option value="paid">{{ __('Paid') }}</option>
                    <option value="unpaid">{{ __('Unpaid') }}</option>
                </flux:select>
                <flux:input wire:model.live="filterFrom" type="date" :label="__('From')" class="max-w-[160px]" />
                <flux:input wire:model.live="filterTo" type="date" :label="__('To')" class="max-w-[160px]" />
                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" size="sm"
                        wire:click="$set('filterVehicle', ''); $set('filterType', ''); $set('filterPaid', ''); $set('filterFrom', ''); $set('filterTo', '')">
                        {{ __('Clear filters') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:card>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit entry') : __('New vehicle finance entry') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                    <option value="">{{ __('— Select vehicle —') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="finance_type" :label="__('Type')">
                    @foreach (\App\Enums\VehicleFinanceType::cases() as $ft)
                        <option value="{{ $ft->value }}">{{ $ft->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="currency_code" :label="__('Currency')">
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>
                <flux:input wire:model="amount" type="text" :label="__('Amount')" required />
                <flux:input wire:model="transaction_date" type="date" :label="__('Transaction date')" required />
                <flux:input wire:model="due_date" type="date" :label="__('Due date')" />
                <flux:input wire:model="paid_at" type="date" :label="__('Paid at')" />
                <flux:input wire:model="reference_no" :label="__('Reference no.')" class="lg:col-span-2" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" class="lg:col-span-3" />
                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete toolbar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected records?') }}">
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
                        <th class="w-12 py-2 pe-3">
                            <input type="checkbox"
                                   wire:click.prevent="toggleSelectPage"
                                   @checked($this->isPageFullySelected())
                                   class="rounded border-zinc-300" />
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Vehicle') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('finance_type')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Type') }}@if ($sortColumn === 'finance_type') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('amount')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Amount') }}@if ($sortColumn === 'amount') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('transaction_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Date') }}@if ($sortColumn === 'transaction_date') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Due date') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('paid_at')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Paid') }}@if ($sortColumn === 'paid_at') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedFinances as $vf)
                        <tr class="{{ $vf->paid_at === null && $vf->due_date?->isPast() ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model="selectedIds" :value="$vf->id" class="rounded border-zinc-300" />
                            </td>
                            <td class="py-2 pe-3 font-mono text-xs font-medium">{{ $vf->vehicle?->plate ?? '—' }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $vf->finance_type->color() }}" size="sm">{{ $vf->finance_type->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs font-semibold">
                                {{ number_format((float) $vf->amount, 2) }} {{ $vf->currency_code }}
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">{{ $vf->transaction_date->format('d M Y') }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs {{ $vf->paid_at === null && $vf->due_date?->isPast() ? 'font-semibold text-red-600' : '' }}">
                                {{ $vf->due_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">
                                @if ($vf->paid_at)
                                    <flux:badge color="green" size="sm">{{ $vf->paid_at->format('d M Y') }}</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">{{ __('Unpaid') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    @can('update', $vf)
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $vf->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        @if (! $vf->paid_at)
                                            <flux:button size="sm" variant="primary" wire:click="markPaid({{ $vf->id }})">
                                                {{ __('Mark paid') }}
                                            </flux:button>
                                        @endif
                                    @endcan
                                    @can('delete', $vf)
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $vf->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No vehicle finance records yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedFinances->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete record?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
