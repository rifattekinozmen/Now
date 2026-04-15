<?php

use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\CurrentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Account transactions')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $currentAccountId  = '';
    public string $transactionType   = '';
    public string $amount            = '';
    public string $currencyCode      = 'TRY';
    public string $exchangeRate      = '1';
    public string $transactionDate   = '';
    public string $dueDate           = '';
    public string $referenceNo       = '';
    public string $description       = '';

    // Filters
    public string $filterAccount   = '';
    public string $filterType      = '';
    public string $filterDateFrom  = '';
    public string $filterDateTo    = '';
    public string $filterOverdue   = '';

    public string $sortColumn    = 'transaction_date';
    public string $sortDirection = 'desc';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', AccountTransaction::class);
        $this->transactionDate = now()->format('Y-m-d');
        $this->transactionType = TransactionType::Debit->value;
    }

    public function updatedFilterAccount(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }
    public function updatedFilterOverdue(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['transaction_date', 'amount', 'due_date', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    /**
     * @return array{total:int, total_debit:float, total_credit:float, overdue:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $base = AccountTransaction::query();

        return [
            'total'        => $base->clone()->count(),
            'total_debit'  => (float) $base->clone()->whereIn('transaction_type', [
                TransactionType::Debit->value,
                TransactionType::Advance->value,
            ])->sum('amount'),
            'total_credit' => (float) $base->clone()->whereIn('transaction_type', [
                TransactionType::Credit->value,
                TransactionType::Payment->value,
                TransactionType::Return->value,
            ])->sum('amount'),
            'overdue' => (int) $base->clone()
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->whereNotIn('transaction_type', [TransactionType::Payment->value, TransactionType::Return->value])
                ->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CurrentAccount>
     */
    #[Computed]
    public function currentAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return CurrentAccount::query()->orderBy('name')->get(['id', 'name', 'code', 'account_type']);
    }

    private function transactionQuery(): Builder
    {
        $q = AccountTransaction::query()->with(['currentAccount']);

        if ($this->filterAccount !== '') {
            $q->where('current_account_id', $this->filterAccount);
        }

        if ($this->filterType !== '') {
            $q->where('transaction_type', $this->filterType);
        }

        if ($this->filterDateFrom !== '') {
            $q->where('transaction_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->where('transaction_date', '<=', $this->filterDateTo);
        }

        if ($this->filterOverdue === '1') {
            $q->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->whereNotIn('transaction_type', [TransactionType::Payment->value, TransactionType::Return->value]);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection);
    }

    #[Computed]
    public function paginatedTransactions(): LengthAwarePaginator
    {
        return $this->transactionQuery()->paginate(25);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $tx = AccountTransaction::query()->findOrFail($id);

        $this->editingId          = $id;
        $this->currentAccountId   = (string) $tx->current_account_id;
        $this->transactionType    = $tx->transaction_type instanceof TransactionType
            ? $tx->transaction_type->value
            : (string) $tx->transaction_type;
        $this->amount             = (string) $tx->amount;
        $this->currencyCode       = $tx->currency_code;
        $this->exchangeRate       = (string) $tx->exchange_rate;
        $this->transactionDate    = $tx->transaction_date?->format('Y-m-d') ?? '';
        $this->dueDate            = $tx->due_date?->format('Y-m-d') ?? '';
        $this->referenceNo        = $tx->reference_no ?? '';
        $this->description        = $tx->description ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'currentAccountId' => ['required', 'integer', 'exists:current_accounts,id'],
            'transactionType'  => ['required', 'string'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'currencyCode'     => ['required', 'string', 'size:3'],
            'exchangeRate'     => ['required', 'numeric', 'min:0'],
            'transactionDate'  => ['required', 'date'],
            'dueDate'          => ['nullable', 'date'],
            'referenceNo'      => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ], [], [
            'currentAccountId' => __('Current account'),
            'transactionType'  => __('Transaction type'),
            'transactionDate'  => __('Transaction date'),
        ]);

        $data = [
            'current_account_id' => (int) $validated['currentAccountId'],
            'transaction_type'   => $validated['transactionType'],
            'amount'             => $validated['amount'],
            'currency_code'      => $validated['currencyCode'],
            'exchange_rate'      => $validated['exchangeRate'],
            'transaction_date'   => $validated['transactionDate'],
            'due_date'           => $validated['dueDate'] ?: null,
            'reference_no'       => $validated['referenceNo'] ?: null,
            'description'        => $validated['description'] ?: null,
        ];

        if ($this->editingId && $this->editingId > 0) {
            $tx = AccountTransaction::query()->findOrFail($this->editingId);
            $tx->update($data);
        } else {
            AccountTransaction::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete-tx')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            AccountTransaction::query()->findOrFail($this->confirmingDeleteId)->delete();
            $this->confirmingDeleteId = null;
            $this->modal('confirm-delete-tx')->close();
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->filterAccount  = '';
        $this->filterType     = '';
        $this->filterDateFrom = '';
        $this->filterDateTo   = '';
        $this->filterOverdue  = '';
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->currentAccountId = '';
        $this->transactionType  = TransactionType::Debit->value;
        $this->amount           = '';
        $this->currencyCode     = 'TRY';
        $this->exchangeRate     = '1';
        $this->transactionDate  = now()->format('Y-m-d');
        $this->dueDate          = '';
        $this->referenceNo      = '';
        $this->description      = '';
    }

    private function typeColor(string $type): string
    {
        return match ($type) {
            'debit'   => 'red',
            'credit'  => 'green',
            'payment' => 'blue',
            'return'  => 'amber',
            'advance' => 'purple',
            default   => 'zinc',
        };
    }

    private function typeLabel(string $type): string
    {
        $case = TransactionType::tryFrom($type);

        return $case !== null ? $case->label() : $type;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Account transactions')"
        :description="__('Debit, credit and payment entries across all current accounts.')"
    >
        <x-slot name="actions">
            <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                {{ __('New transaction') }}
            </flux:button>
            <flux:button :href="route('admin.finance.current-accounts.index')" variant="outline" wire:navigate>
                {{ __('Current accounts') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total records') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total debit') }}</flux:text>
            <flux:heading size="lg" class="text-red-600">{{ number_format($this->kpiStats['total_debit'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total credit') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ number_format($this->kpiStats['total_credit'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 {{ $this->kpiStats['overdue'] > 0 ? 'ring-2 ring-red-400' : '' }}">
            <flux:text class="text-sm {{ $this->kpiStats['overdue'] > 0 ? 'text-red-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                {{ __('Overdue') }}
            </flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['overdue'] > 0 ? 'text-red-600' : '' }}">
                {{ $this->kpiStats['overdue'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit transaction') : __('New transaction') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="currentAccountId" :label="__('Current account')" required>
                    <option value="">{{ __('Select account…') }}</option>
                    @foreach ($this->currentAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}{{ $acc->code ? ' ('.$acc->code.')' : '' }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="transactionType" :label="__('Transaction type')" required>
                    @foreach (\App\Enums\TransactionType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" required />
                <flux:select wire:model="currencyCode" :label="__('Currency')">
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                </flux:select>
                <flux:input wire:model="exchangeRate" type="number" step="0.000001" min="0" :label="__('Exchange rate')" />
                <flux:input wire:model="transactionDate" type="date" :label="__('Transaction date')" required />
                <flux:input wire:model="dueDate" type="date" :label="__('Due date')" />
                <flux:input wire:model="referenceNo" :label="__('Reference no')" class="max-w-xs" />
                <flux:input wire:model="description" :label="__('Description')" class="sm:col-span-2 lg:col-span-1" />
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="filterAccount" :label="__('Account')" class="max-w-[220px]">
            <option value="">{{ __('All accounts') }}</option>
            @foreach ($this->currentAccounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[160px]">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\TransactionType::cases() as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From')" class="max-w-[160px]" />
        <flux:input wire:model.live="filterDateTo" type="date" :label="__('To')" class="max-w-[160px]" />
        <div class="flex items-end gap-2 pb-0.5">
            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <flux:checkbox wire:model.live="filterOverdue" value="1" />
                <span class="text-red-600 dark:text-red-400">{{ __('Overdue only') }}</span>
            </label>
        </div>
        @if ($filterAccount !== '' || $filterType !== '' || $filterDateFrom !== '' || $filterDateTo !== '' || $filterOverdue !== '')
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 text-start font-medium">{{ __('Account') }}</th>
                        <th class="py-2 pe-3 text-start font-medium">{{ __('Type') }}</th>
                        <th class="py-2 pe-3 text-start font-medium">
                            <button wire:click="sortBy('transaction_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Date') }}
                                @if ($sortColumn === 'transaction_date') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 text-start font-medium">
                            <button wire:click="sortBy('due_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Due date') }}
                                @if ($sortColumn === 'due_date') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 text-start font-medium">{{ __('Reference') }}</th>
                        <th class="py-2 pe-3 text-end font-medium">
                            <button wire:click="sortBy('amount')" class="flex items-center justify-end gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Amount') }}
                                @if ($sortColumn === 'amount') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedTransactions as $tx)
                        @php
                            $isOverdue = $tx->due_date && $tx->due_date->lt(now())
                                && ! in_array($tx->transaction_type?->value ?? $tx->transaction_type, ['payment', 'return'], true);
                        @endphp
                        <tr wire:key="tx-{{ $tx->id }}" class="{{ $isOverdue ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                            <td class="py-2 pe-3">
                                <div class="font-medium">{{ $tx->currentAccount?->name ?? '—' }}</div>
                                @if ($tx->currentAccount?->code)
                                    <div class="font-mono text-xs text-zinc-400">{{ $tx->currentAccount->code }}</div>
                                @endif
                            </td>
                            <td class="py-2 pe-3">
                                @php $typeVal = $tx->transaction_type instanceof TransactionType ? $tx->transaction_type->value : (string) $tx->transaction_type; @endphp
                                <flux:badge color="{{ $this->typeColor($typeVal) }}" size="sm">
                                    {{ $this->typeLabel($typeVal) }}
                                </flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-400">
                                {{ $tx->transaction_date?->format('d M Y') }}
                            </td>
                            <td class="py-2 pe-3 {{ $isOverdue ? 'font-semibold text-red-600' : 'text-zinc-600 dark:text-zinc-400' }}">
                                {{ $tx->due_date?->format('d M Y') ?? '—' }}
                                @if ($isOverdue)
                                    <flux:badge color="red" size="sm" class="ms-1">{{ __('Overdue') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-2 pe-3 font-mono text-xs text-zinc-500">
                                {{ $tx->reference_no ?? '—' }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono font-semibold">
                                {{ number_format((float) $tx->amount, 2) }}
                                <span class="ms-1 text-xs font-normal text-zinc-400">{{ $tx->currency_code }}</span>
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $tx->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $tx->id }})">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No account transactions found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedTransactions->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete-tx" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete transaction?') }}</flux:heading>
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
