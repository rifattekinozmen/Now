<?php

use App\Authorization\LogisticsPermission;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Bank Transactions')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $bank_account_id = '';

    public string $transaction_date = '';

    public string $amount = '';

    public string $currency_code = 'TRY';

    public string $transaction_type = 'credit';

    public string $reference_no = '';

    public string $description = '';

    // Filters
    public bool $filtersOpen = false;

    public string $filterSearch = '';

    public string $filterType = '';

    public string $filterAccount = '';

    public string $filterReconciled = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public string $sortColumn = 'transaction_date';

    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', BankTransaction::class);
        $this->transaction_date = now()->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAccount(): void
    {
        $this->resetPage();
    }

    public function updatedFilterReconciled(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'transaction_date', 'amount', 'transaction_type'];
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
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedTransactions->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedTransactions->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', BankTransaction::class);
        $count = BankTransaction::query()
            ->whereIn('id', $this->selectedIds)
            ->where('is_reconciled', false)
            ->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    /**
     * @return array{total_credits:float, total_debits:float, unreconciled:int, this_month:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $totalCredits = (float) BankTransaction::query()->credits()->sum('amount');
        $totalDebits = (float) BankTransaction::query()->debits()->sum('amount');

        return [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_balance' => $totalCredits - $totalDebits,
            'unreconciled' => BankTransaction::query()->unreconciled()->count(),
            'this_month' => BankTransaction::query()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->count(),
        ];
    }

    /**
     * @return Collection<int, BankAccount>
     */
    #[Computed]
    public function bankAccounts(): Collection
    {
        return BankAccount::query()->orderBy('bank_name')->get();
    }

    private function transactionsQuery(): Builder
    {
        $q = BankTransaction::query()->with(['bankAccount']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('reference_no', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('transaction_type', $this->filterType);
        }

        if ($this->filterAccount !== '') {
            $q->where('bank_account_id', (int) $this->filterAccount);
        }

        if ($this->filterReconciled !== '') {
            $q->where('is_reconciled', $this->filterReconciled === '1');
        }

        if ($this->filterDateFrom !== '') {
            $q->whereDate('transaction_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->whereDate('transaction_date', '<=', $this->filterDateTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedTransactions(): LengthAwarePaginator
    {
        return $this->transactionsQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', BankTransaction::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $tx = BankTransaction::query()->findOrFail($id);
        Gate::authorize('update', $tx);

        $this->editingId = $id;
        $this->bank_account_id = (string) $tx->bank_account_id;
        $this->transaction_date = $tx->transaction_date->format('Y-m-d');
        $this->amount = (string) $tx->amount;
        $this->currency_code = $tx->currency_code;
        $this->transaction_type = $tx->transaction_type->value;
        $this->reference_no = $tx->reference_no ?? '';
        $this->description = $tx->description ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::FINANCE_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'currency_code' => ['required', 'in:TRY,USD,EUR,GBP'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'bank_account_id' => (int) $validated['bank_account_id'],
            'transaction_date' => $validated['transaction_date'],
            'amount' => $validated['amount'],
            'currency_code' => $validated['currency_code'],
            'transaction_type' => $validated['transaction_type'],
            'reference_no' => filled($validated['reference_no']) ? $validated['reference_no'] : null,
            'description' => filled($validated['description']) ? $validated['description'] : null,
        ];

        if ($this->editingId === 0) {
            Gate::authorize('create', BankTransaction::class);
            BankTransaction::query()->create($data);
            session()->flash('success', __('Bank transaction created.'));
        } else {
            $tx = BankTransaction::query()->findOrFail($this->editingId);
            Gate::authorize('update', $tx);
            $tx->update($data);
            session()->flash('success', __('Bank transaction updated.'));
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function reconcile(int $id): void
    {
        $tx = BankTransaction::query()->findOrFail($id);
        Gate::authorize('reconcile', $tx);
        $tx->update(['is_reconciled' => true]);
        session()->flash('success', __('Transaction #:id marked as reconciled.', ['id' => $id]));
    }

    public function delete(int $id): void
    {
        $tx = BankTransaction::query()->findOrFail($id);
        Gate::authorize('delete', $tx);
        $tx->delete();
        session()->flash('success', __('Bank transaction deleted.'));
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->bank_account_id = '';
        $this->transaction_date = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $this->amount = '';
        $this->currency_code = 'TRY';
        $this->transaction_type = 'credit';
        $this->reference_no = '';
        $this->description = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser  = auth()->user();
        $canWrite  = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::FINANCE_WRITE);
        $canAdmin  = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    <x-admin.page-header
        :heading="__('Bank Transactions')"
        :description="__('Record and reconcile bank movements. Use Finance → Bank statement CSV import for file-based intake; a separate bank document archive is not required for MVP.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <x-admin.index-actions>
                    <x-slot name="extra">
                        <flux:button :href="route('admin.finance.bank-statement-csv')" variant="outline" wire:navigate icon="arrow-up-tray">
                            {{ __('CSV import') }}
                        </flux:button>
                    </x-slot>
                    <x-slot name="primary">
                        <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                            {{ __('New transaction') }}
                        </flux:button>
                    </x-slot>
                </x-admin.index-actions>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- Flash messages --}}
    @if (session()->has('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif
    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success">{{ session('bulk_deleted') }}</flux:callout>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-5">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total credits') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">
                {{ number_format((float) $this->kpiStats['total_credits'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total debits') }}</flux:text>
            <flux:heading size="lg" class="text-red-500">
                {{ number_format((float) $this->kpiStats['total_debits'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Net balance') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['net_balance'] >= 0 ? 'text-blue-600' : 'text-red-500' }}">
                {{ number_format((float) $this->kpiStats['net_balance'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unreconciled') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['unreconciled'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['unreconciled'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This month') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['this_month'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Inline form --}}
    @if ($editingId !== null)
        <flux:card class="p-6">
            <flux:heading size="md" class="mb-4">
                {{ $editingId === 0 ? __('New transaction') : __('Edit transaction') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Bank account') }} *</flux:label>
                    <flux:select wire:model="bank_account_id">
                        <option value="">— {{ __('Select') }} —</option>
                        @foreach ($this->bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->bank_name }} ({{ $ba->currency_code }})</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="bank_account_id" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Transaction date') }} *</flux:label>
                    <flux:input type="date" wire:model="transaction_date" />
                    <flux:error name="transaction_date" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Type') }} *</flux:label>
                    <flux:select wire:model="transaction_type">
                        <option value="credit">{{ __('Credit') }}</option>
                        <option value="debit">{{ __('Debit') }}</option>
                    </flux:select>
                    <flux:error name="transaction_type" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Amount') }} *</flux:label>
                    <flux:input type="number" step="0.01" min="0.01" wire:model="amount" />
                    <flux:error name="amount" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Currency') }} *</flux:label>
                    <flux:select wire:model="currency_code">
                        <option value="TRY">TRY</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </flux:select>
                    <flux:error name="currency_code" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Reference no') }}</flux:label>
                    <flux:input type="text" wire:model="reference_no" />
                    <flux:error name="reference_no" />
                </flux:field>
                <flux:field class="sm:col-span-2 lg:col-span-3">
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:input type="text" wire:model="description" />
                    <flux:error name="description" />
                </flux:field>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex items-center justify-between gap-2">
            <flux:input
                wire:model.live.debounce.300ms="filterSearch"
                :placeholder="__('Search reference, description…')"
                icon="magnifying-glass"
                class="max-w-xs"
            />
            <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}">
                {{ __('Filters') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model.live="filterType">
                        <option value="">{{ __('All') }}</option>
                        <option value="credit">{{ __('Credit') }}</option>
                        <option value="debit">{{ __('Debit') }}</option>
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Account') }}</flux:label>
                    <flux:select wire:model.live="filterAccount">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($this->bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->bank_name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Reconciled') }}</flux:label>
                    <flux:select wire:model.live="filterReconciled">
                        <option value="">{{ __('All') }}</option>
                        <option value="1">{{ __('Yes') }}</option>
                        <option value="0">{{ __('No') }}</option>
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Date from') }}</flux:label>
                    <flux:input type="date" wire:model.live="filterDateFrom" />
                </flux:field>
            </div>
            @if ($filterType || $filterAccount || $filterReconciled || $filterDateFrom || $filterDateTo)
                <div class="mt-2">
                    <flux:button variant="ghost" size="sm"
                        wire:click="$set('filterType',''); $set('filterAccount',''); $set('filterReconciled',''); $set('filterDateFrom',''); $set('filterDateTo','')">
                        {{ __('Clear filters') }}
                    </flux:button>
                </div>
            @endif
        @endif
    </flux:card>

    {{-- Bulk actions --}}
    @if (count($selectedIds) > 0)
        <div class="flex items-center gap-3 rounded-lg bg-yellow-50 p-3 dark:bg-yellow-900/20">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button variant="danger" size="sm" wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected transactions? Reconciled records will be skipped.') }}">
                {{ __('Delete selected') }}
            </flux:button>
            <flux:button variant="ghost" size="sm" wire:click="$set('selectedIds', [])">
                {{ __('Clear') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="overflow-x-auto p-0">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr class="text-left text-zinc-500">
                    <th class="w-10 px-4 py-3">
                        <flux:checkbox wire:click="toggleSelectPage"
                            :checked="$this->isPageFullySelected()" />
                    </th>
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('id')">#
                        @if ($sortColumn === 'id')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('transaction_date')">{{ __('Date') }}
                        @if ($sortColumn === 'transaction_date')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </th>
                    <th class="px-4 py-3">{{ __('Account') }}</th>
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('transaction_type')">{{ __('Type') }}
                        @if ($sortColumn === 'transaction_type')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3" wire:click="sortBy('amount')">{{ __('Amount') }}
                        @if ($sortColumn === 'amount')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </th>
                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                    <th class="px-4 py-3">{{ __('Description') }}</th>
                    <th class="px-4 py-3">{{ __('Reconciled') }}</th>
                    <th class="px-4 py-3">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->paginatedTransactions as $tx)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-2">
                            <flux:checkbox wire:model.live="selectedIds" value="{{ $tx->id }}" />
                        </td>
                        <td class="px-4 py-2 text-zinc-400">{{ $tx->id }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $tx->transaction_date->format('d.m.Y') }}</td>
                        <td class="px-4 py-2 text-xs">{{ $tx->bankAccount?->bank_name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <flux:badge color="{{ $tx->transaction_type->color() }}" size="sm">
                                {{ $tx->transaction_type->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-2 font-semibold whitespace-nowrap
                            {{ $tx->transaction_type === \App\Enums\BankTransactionType::Credit ? 'text-green-600' : 'text-red-500' }}">
                            {{ number_format((float) $tx->amount, 2) }}
                            <span class="text-xs text-zinc-400">{{ $tx->currency_code }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $tx->reference_no ?? '—' }}</td>
                        <td class="px-4 py-2 max-w-xs truncate text-zinc-600 dark:text-zinc-400">
                            {{ $tx->description ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($tx->is_reconciled)
                                <flux:badge color="green" size="sm">{{ __('Yes') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('No') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1">
                                @if ($canAdmin && ! $tx->is_reconciled)
                                    <flux:button size="sm" variant="primary"
                                        wire:click="reconcile({{ $tx->id }})"
                                        wire:confirm="{{ __('Mark transaction #:id as reconciled?', ['id' => $tx->id]) }}">
                                        {{ __('Reconcile') }}
                                    </flux:button>
                                @endif
                                @if ($canWrite && ! $tx->is_reconciled)
                                    <flux:button size="sm" variant="ghost" icon="pencil"
                                        wire:click="edit({{ $tx->id }})" />
                                    <flux:button size="sm" variant="danger" icon="trash"
                                        wire:click="delete({{ $tx->id }})"
                                        wire:confirm="{{ __('Delete this transaction?') }}" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-zinc-400">
                            {{ __('No bank transactions found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </flux:card>

    {{-- Pagination --}}
    <div>{{ $this->paginatedTransactions->links() }}</div>
</div>
