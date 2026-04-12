<?php

use App\Models\ChartAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Chart of accounts')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $code = '';
    public string $name = '';
    public string $type = 'asset';

    // Filters
    public string $filterSearch = '';
    public string $filterType   = '';

    public string $sortColumn    = 'code';
    public string $sortDirection = 'asc';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', ChartAccount::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['code', 'name', 'type'];
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

    /**
     * @return array{total:int, asset:int, liability:int, revenue:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'     => ChartAccount::query()->count(),
            'asset'     => ChartAccount::query()->where('type', 'asset')->count(),
            'liability' => ChartAccount::query()->where('type', 'liability')->count(),
            'revenue'   => ChartAccount::query()->where('type', 'revenue')->count(),
        ];
    }

    private function accountQuery(): Builder
    {
        $q = ChartAccount::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection);
    }

    #[Computed]
    public function paginatedAccounts(): LengthAwarePaginator
    {
        return $this->accountQuery()->paginate(30);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', ChartAccount::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $account = ChartAccount::query()->findOrFail($id);
        Gate::authorize('update', $account);

        $this->editingId = $id;
        $this->code      = $account->code;
        $this->name      = $account->name;
        $this->type      = $account->type;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
        ]);

        if ($this->editingId && $this->editingId > 0) {
            $account = ChartAccount::query()->findOrFail($this->editingId);
            Gate::authorize('update', $account);
            $account->update($validated);
        } else {
            Gate::authorize('create', ChartAccount::class);
            ChartAccount::query()->create($validated);
        }

        $this->editingId = null;
        $this->resetForm();
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
            $account = ChartAccount::query()->findOrFail($this->confirmingDeleteId);
            Gate::authorize('delete', $account);
            $account->delete();
            $this->confirmingDeleteId = null;
            $this->resetPage();
        }
    }

    private function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->type = 'asset';
    }

    /** @return string */
    private function typeLabel(string $type): string
    {
        return match ($type) {
            'asset'     => __('Asset'),
            'liability' => __('Liability'),
            'equity'    => __('Equity'),
            'revenue'   => __('Revenue'),
            'expense'   => __('Expense'),
            default     => $type,
        };
    }

    /** @return string */
    private function typeColor(string $type): string
    {
        return match ($type) {
            'asset'     => 'blue',
            'liability' => 'red',
            'equity'    => 'purple',
            'revenue'   => 'green',
            'expense'   => 'orange',
            default     => 'zinc',
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Chart of accounts')"
        :description="__('Manage your general ledger account structure.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\ChartAccount::class)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New account') }}
                </flux:button>
            @endcan
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>
                {{ __('Back to finance') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total accounts') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Asset') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->kpiStats['asset'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Liability') }}</flux:text>
            <flux:heading size="lg" class="text-red-500">{{ $this->kpiStats['liability'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Revenue') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['revenue'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit account') : __('New account') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="code" :label="__('Code')" required />
                <flux:input wire:model="name" :label="__('Name')" required class="sm:col-span-2" />
                <flux:select wire:model="type" :label="__('Type')">
                    <option value="asset">{{ __('Asset') }}</option>
                    <option value="liability">{{ __('Liability') }}</option>
                    <option value="equity">{{ __('Equity') }}</option>
                    <option value="revenue">{{ __('Revenue') }}</option>
                    <option value="expense">{{ __('Expense') }}</option>
                </flux:select>
                <div class="flex flex-wrap gap-2 sm:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live="filterSearch" :label="__('Search')" :placeholder="__('Code or name…')" class="max-w-[240px]" />
        <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[180px]">
            <option value="">{{ __('All types') }}</option>
            <option value="asset">{{ __('Asset') }}</option>
            <option value="liability">{{ __('Liability') }}</option>
            <option value="equity">{{ __('Equity') }}</option>
            <option value="revenue">{{ __('Revenue') }}</option>
            <option value="expense">{{ __('Expense') }}</option>
        </flux:select>
        @if ($filterSearch !== '' || $filterType !== '')
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm"
                    wire:click="$set('filterSearch', ''); $set('filterType', '')">
                    {{ __('Clear') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('code')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Code') }}@if ($sortColumn === 'code') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Name') }}@if ($sortColumn === 'name') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('type')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Type') }}@if ($sortColumn === 'type') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAccounts as $account)
                        <tr>
                            <td class="py-2 pe-3 font-mono text-xs font-semibold">{{ $account->code }}</td>
                            <td class="py-2 pe-3">{{ $account->name }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $this->typeColor($account->type) }}" size="sm">
                                    {{ $this->typeLabel($account->type) }}
                                </flux:badge>
                            </td>
                            <td class="py-2 text-end">
                                @can('update', $account)
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $account->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $account->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-zinc-500">
                                {{ __('No accounts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedAccounts->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete account?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Journal lines referencing this account will be affected.') }}</flux:text>
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
