<?php

use App\Authorization\LogisticsPermission;
use App\Enums\AccountType;
use App\Models\CurrentAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Current Accounts')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $account_type = 'customer';
    public ?int $customer_id = null;
    public ?int $employee_id = null;
    public ?int $vehicle_id = null;
    public string $code = '';
    public string $name = '';
    public string $currency_code = 'TRY';
    public string $credit_limit = '0';
    public string $payment_term_days = '30';
    public bool $is_active = true;
    public string $notes = '';

    public bool $filtersOpen = false;

    public string $filterSearch = '';
    public string $filterType = '';
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
        Gate::authorize('viewAny', CurrentAccount::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterCurrency(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'name', 'code', 'account_type', 'balance', 'currency_code', 'created_at'];
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

    public function updatedAccountType(): void
    {
        $this->customer_id = null;
        $this->employee_id = null;
        $this->vehicle_id  = null;
    }

    /**
     * @return array{total: int, active: int, total_balance_try: float, overdue_count: int}
     */
    #[Computed(persist: true, seconds: 300)]
    public function kpiStats(): array
    {
        $all = CurrentAccount::query()->get();

        $overdueCount = CurrentAccount::query()
            ->whereHas('transactions', fn (Builder $q) => $q->overdue())
            ->count();

        return [
            'total'             => $all->count(),
            'active'            => $all->where('is_active', true)->count(),
            'total_balance_try' => (float) $all->where('currency_code', 'TRY')->sum('balance'),
            'overdue_count'     => $overdueCount,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Customer> */
    #[Computed]
    public function customers(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->orderBy('legal_name')->get(['id', 'legal_name']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Employee> */
    #[Computed]
    public function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Vehicle> */
    #[Computed]
    public function vehicles(): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()->orderBy('plate')->get(['id', 'plate']);
    }

    private function accountsQuery(): Builder
    {
        $q = CurrentAccount::query()->with(['customer', 'employee', 'vehicle']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(fn (Builder $qq) => $qq->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        if ($this->filterType !== '') {
            $q->where('account_type', $this->filterType);
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
    public function paginatedAccounts(): LengthAwarePaginator
    {
        return $this->accountsQuery()->paginate(15);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', CurrentAccount::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $row = CurrentAccount::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId         = $row->id;
        $this->account_type      = $row->account_type->value;
        $this->customer_id       = $row->customer_id;
        $this->employee_id       = $row->employee_id;
        $this->vehicle_id        = $row->vehicle_id;
        $this->code              = $row->code ?? '';
        $this->name              = $row->name;
        $this->currency_code     = $row->currency_code;
        $this->credit_limit      = (string) $row->credit_limit;
        $this->payment_term_days = (string) $row->payment_term_days;
        $this->is_active         = $row->is_active;
        $this->notes             = $row->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedAccounts->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedAccounts->pluck('id')->toArray();

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
        CurrentAccount::query()
            ->whereIn('id', $this->selectedIds)
            ->where('tenant_id', $authUser->tenant_id)
            ->delete();
        $this->selectedIds = [];
        $this->resetPage();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::CURRENT_ACCOUNTS_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'account_type'      => ['required', 'in:customer,employee,vehicle,supplier'],
            'customer_id'       => ['nullable', 'integer', 'exists:customers,id'],
            'employee_id'       => ['nullable', 'integer', 'exists:employees,id'],
            'vehicle_id'        => ['nullable', 'integer', 'exists:vehicles,id'],
            'code'              => ['nullable', 'string', 'max:32'],
            'name'              => ['required', 'string', 'max:160'],
            'currency_code'     => ['required', 'in:TRY,USD,EUR,GBP'],
            'credit_limit'      => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active'         => ['boolean'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'account_type'      => $validated['account_type'],
            'customer_id'       => $validated['account_type'] === 'customer' ? ($validated['customer_id'] ?: null) : null,
            'employee_id'       => $validated['account_type'] === 'employee' ? ($validated['employee_id'] ?: null) : null,
            'vehicle_id'        => $validated['account_type'] === 'vehicle' ? ($validated['vehicle_id'] ?: null) : null,
            'code'              => filled($validated['code']) ? $validated['code'] : null,
            'name'              => $validated['name'],
            'currency_code'     => $validated['currency_code'],
            'credit_limit'      => $validated['credit_limit'],
            'payment_term_days' => $validated['payment_term_days'],
            'is_active'         => $validated['is_active'],
            'notes'             => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = CurrentAccount::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', CurrentAccount::class);
            CurrentAccount::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $row = CurrentAccount::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $row->update(['is_active' => ! $row->is_active]);
    }

    public function delete(int $id): void
    {
        $row = CurrentAccount::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->account_type      = 'customer';
        $this->customer_id       = null;
        $this->employee_id       = null;
        $this->vehicle_id        = null;
        $this->code              = '';
        $this->name              = '';
        $this->currency_code     = 'TRY';
        $this->credit_limit      = '0';
        $this->payment_term_days = '30';
        $this->is_active         = true;
        $this->notes             = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::CURRENT_ACCOUNTS_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Current accounts')"
        :description="__('Manage customer, employee and vehicle ledger accounts.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New account') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total accounts') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total TRY balance') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_balance_try'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overdue') }}</flux:text>
            <flux:heading size="lg" @class(['text-red-600' => $this->kpiStats['overdue_count'] > 0])>
                {{ $this->kpiStats['overdue_count'] }}
            </flux:heading>
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
            <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[160px]">
                <option value="">{{ __('All types') }}</option>
                @foreach (\App\Enums\AccountType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterCurrency" :label="__('Currency')" class="max-w-[120px]">
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
                {{ $editingId === 0 ? __('New current account') : __('Edit current account') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="account_type" :label="__('Account type')" required>
                    @foreach (\App\Enums\AccountType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>

                @if ($account_type === 'customer')
                    <flux:select wire:model="customer_id" :label="__('Customer')">
                        <option value="">{{ __('— Select customer —') }}</option>
                        @foreach ($this->customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </flux:select>
                @elseif ($account_type === 'employee')
                    <flux:select wire:model="employee_id" :label="__('Employee')">
                        <option value="">{{ __('— Select employee —') }}</option>
                        @foreach ($this->employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </flux:select>
                @elseif ($account_type === 'vehicle')
                    <flux:select wire:model="vehicle_id" :label="__('Vehicle')">
                        <option value="">{{ __('— Select vehicle —') }}</option>
                        @foreach ($this->vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->plate }}</option>
                        @endforeach
                    </flux:select>
                @else
                    <div></div>
                @endif

                <flux:input wire:model="name" :label="__('Account name')" required />
                <flux:input wire:model="code" :label="__('Code')" placeholder="e.g. CAR-001" />
                <flux:select wire:model="currency_code" :label="__('Currency')" required>
                    <option value="TRY">TRY — Turkish Lira</option>
                    <option value="USD">USD — US Dollar</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="GBP">GBP — British Pound</option>
                </flux:select>
                <flux:input wire:model="credit_limit" type="number" step="0.01" min="0" :label="__('Credit limit')" />
                <flux:input wire:model="payment_term_days" type="number" min="0" max="365" :label="__('Payment term (days)')" />
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
                            <th class="py-2 ps-2 pe-2 w-8">
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
                        <th class="py-2 pe-4 font-medium">
                            <button wire:click="sortBy('account_type')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Type') }}@if ($sortColumn === 'account_type') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Related to') }}</th>
                        <th class="py-2 pe-4 font-medium text-end">
                            <button wire:click="sortBy('balance')" class="flex items-center gap-1 ms-auto hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Balance') }}@if ($sortColumn === 'balance') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-4 font-medium">{{ __('Term') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Status') }}</th>
                        @if ($canWrite)
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAccounts as $row)
                        <tr>
                            @if ($canWrite)
                                <td class="py-2 ps-2 pe-2">
                                    <flux:checkbox wire:model.live="selectedIds" :value="(int) $row->id" />
                                </td>
                            @endif
                            <td class="py-2 pe-4 font-medium">{{ $row->name }}</td>
                            <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $row->code ?? '—' }}</td>
                            <td class="py-2 pe-4">
                                <flux:badge :color="$row->account_type->color()" size="sm">
                                    {{ $row->account_type->label() }}
                                </flux:badge>
                            </td>
                            <td class="py-2 pe-4 text-zinc-500">
                                @if ($row->account_type->value === 'customer' && $row->customer)
                                    <a href="{{ route('admin.customers.show', $row->customer) }}" class="text-primary hover:underline" wire:navigate>
                                        {{ $row->customer->name }}
                                    </a>
                                @elseif ($row->account_type->value === 'employee' && $row->employee)
                                    <a href="{{ route('admin.employees.show', $row->employee) }}" class="text-primary hover:underline" wire:navigate>
                                        {{ $row->employee->name }}
                                    </a>
                                @elseif ($row->account_type->value === 'vehicle' && $row->vehicle)
                                    <a href="{{ route('admin.vehicles.show', $row->vehicle) }}" class="text-primary hover:underline" wire:navigate>
                                        {{ $row->vehicle->plate }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pe-4 text-end font-mono">
                                <span @class(['text-red-600' => (float) $row->balance < 0, 'text-green-600' => (float) $row->balance >= 0])>
                                    {{ number_format((float) $row->balance, 2) }}
                                </span>
                                <span class="text-xs text-zinc-400 ms-1">{{ $row->currency_code }}</span>
                            </td>
                            <td class="py-2 pe-4 text-zinc-500">{{ $row->payment_term_days }}d</td>
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
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="confirmAction({{ $row->id }}, 'delete')"
                                    >{{ __('Delete') }}</flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-500">
                                {{ __('No current accounts yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedAccounts->links() }}
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
