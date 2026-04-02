<?php

use App\Authorization\LogisticsPermission;
use App\Models\CashRegister;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Cash Registers')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $currency_code = 'TRY';

    public string $description = '';

    public bool $is_active = true;

    public bool $filtersOpen = false;

    public string $filterSearch = '';

    public string $filterCurrency = '';

    public string $filterStatus = '';

    public string $sortColumn = 'name';
    public string $sortDirection = 'asc';

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingId = null;
    public string $confirmingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CashRegister::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    public function updatedFilterCurrency(): void { $this->resetPage(); }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'name', 'code', 'current_balance', 'currency_code', 'created_at'];
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
        $pageIds = $this->paginatedRegisters->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedRegisters->pluck('id')->toArray();

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
        CashRegister::query()
            ->whereIn('id', $this->selectedIds)
            ->where('tenant_id', $authUser->tenant_id)
            ->delete();
        $this->selectedIds = [];
        $this->resetPage();
    }

    /**
     * @return array{total: int, active: int, total_try: float, total_usd: float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $all = CashRegister::query()->get();

        return [
            'total'     => $all->count(),
            'active'    => $all->where('is_active', true)->count(),
            'total_try' => $all->where('currency_code', 'TRY')->sum('current_balance'),
            'total_usd' => $all->where('currency_code', 'USD')->sum('current_balance'),
        ];
    }

    private function registersQuery(): Builder
    {
        $q = CashRegister::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        if ($this->filterCurrency !== '') {
            $q->where('currency_code', $this->filterCurrency);
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection);
    }

    #[Computed]
    public function paginatedRegisters(): LengthAwarePaginator
    {
        return $this->registersQuery()->paginate(15);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', CashRegister::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $row = CashRegister::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId    = $row->id;
        $this->name         = $row->name;
        $this->code         = $row->code ?? '';
        $this->currency_code= $row->currency_code;
        $this->description  = $row->description ?? '';
        $this->is_active    = $row->is_active;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::CASH_REGISTERS_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:120'],
            'code'          => ['nullable', 'string', 'max:32'],
            'currency_code' => ['required', 'in:TRY,USD,EUR,GBP'],
            'description'   => ['nullable', 'string', 'max:500'],
            'is_active'     => ['boolean'],
        ]);

        $data = [
            'name'          => $validated['name'],
            'code'          => filled($validated['code']) ? $validated['code'] : null,
            'currency_code' => $validated['currency_code'],
            'description'   => filled($validated['description']) ? $validated['description'] : null,
            'is_active'     => $validated['is_active'],
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = CashRegister::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', CashRegister::class);
            CashRegister::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $row = CashRegister::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $row->update(['is_active' => ! $row->is_active]);
    }

    public function delete(int $id): void
    {
        $row = CashRegister::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->name          = '';
        $this->code          = '';
        $this->currency_code = 'TRY';
        $this->description   = '';
        $this->is_active     = true;
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::CASH_REGISTERS_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Cash Registers')"
        :description="__('Manage company cash registers and their balances by currency.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New cash register') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total registers') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total TRY balance') }}</flux:text>
            <flux:heading size="lg">{{ number_format((float) $this->kpiStats['total_try'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total USD balance') }}</flux:text>
            <flux:heading size="lg">{{ number_format((float) $this->kpiStats['total_usd'], 2) }} $</flux:heading>
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
            <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search name / code')" class="max-w-sm" />
            <flux:select wire:model.live="filterCurrency" :label="__('Currency')" class="max-w-[140px]">
                <option value="">{{ __('All') }}</option>
                <option value="TRY">TRY</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
            </flux:select>
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[140px]">
                <option value="">{{ __('All') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </flux:select>
        @endif
    </x-admin.filter-bar>

    {{-- Create / Edit Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId === 0 ? __('New cash register') : __('Edit cash register') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Register name')" required />
                <flux:input wire:model="code" :label="__('Short code')" placeholder="e.g. KAS-001" />
                <flux:select wire:model="currency_code" :label="__('Currency')" required>
                    <option value="TRY">TRY — Turkish Lira</option>
                    <option value="USD">USD — US Dollar</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="GBP">GBP — British Pound</option>
                </flux:select>
                <div class="flex items-end">
                    <flux:checkbox wire:model="is_active" :label="__('Active')" />
                </div>
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" class="sm:col-span-2" />
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
                        <th class="py-2 pe-4 font-medium">
                            <button wire:click="sortBy('code')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Code') }}@if ($sortColumn === 'code') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Currency') }}</th>
                        <th class="py-2 pe-4 font-medium text-end">
                            <button wire:click="sortBy('current_balance')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Balance') }}@if ($sortColumn === 'current_balance') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Vouchers') }}</th>
                        @if ($canWrite)
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedRegisters as $row)
                        <tr>
                            @if ($canWrite)
                                <td class="py-2 pe-2 ps-2">
                                    <flux:checkbox wire:model.live="selectedIds" :value="(int) $row->id" />
                                </td>
                            @endif
                            <td class="py-2 pe-4 font-medium">{{ $row->name }}</td>
                            <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $row->code ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ $row->currency_code }}</td>
                            <td class="py-2 pe-4 text-end font-mono">
                                {{ number_format((float) $row->current_balance, 2) }}
                            </td>
                            <td class="py-2 pe-4">
                                @if ($row->is_active)
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-2 pe-4">
                                <a href="{{ route('admin.finance.vouchers.index', ['cashRegister' => $row->id]) }}"
                                   class="text-xs text-primary hover:underline" wire:navigate>
                                    {{ __('View vouchers') }}
                                </a>
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
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No cash registers yet. Create your first one above.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedRegisters->links() }}
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
