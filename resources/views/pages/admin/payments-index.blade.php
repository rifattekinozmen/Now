<?php

use App\Authorization\LogisticsPermission;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Payments')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $amount = '';
    public string $currency_code = 'TRY';
    public string $payment_date = '';
    public string $due_date = '';
    public string $payment_method = 'bank_transfer';
    public string $reference_no = '';
    public string $bank_account_id = '';
    public string $cash_register_id = '';
    public string $notes = '';

    // Filters
    public bool $filtersOpen = false;
    public string $filterSearch = '';
    public string $filterMethod = '';
    public string $filterStatus = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    public string $sortColumn = 'payment_date';
    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Payment::class);
        $this->payment_date = now()->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterMethod(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'payment_date', 'amount', 'payment_method', 'status'];
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
        $pageIds = $this->paginatedPayments->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedPayments->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', Payment::class);
        $count = Payment::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    /**
     * @return array{pending:int, completed_month:int, total_amount:float, overdue:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'pending'         => Payment::query()->pending()->count(),
            'completed_month' => Payment::query()->completed()
                ->whereMonth('payment_date', now()->month)->count(),
            'total_amount'    => Payment::query()->completed()
                ->whereYear('payment_date', now()->year)->sum('amount'),
            'overdue'         => Payment::query()->pending()
                ->whereNotNull('due_date')
                ->where('due_date', '<', today())->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BankAccount>
     */
    #[Computed]
    public function bankAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return BankAccount::query()->orderBy('bank_name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CashRegister>
     */
    #[Computed]
    public function cashRegisters(): \Illuminate\Database\Eloquent\Collection
    {
        return CashRegister::query()->active()->orderBy('name')->get();
    }

    private function paymentsQuery(): Builder
    {
        $q = Payment::query()->with(['bankAccount', 'cashRegister', 'approvedBy']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('reference_no', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        if ($this->filterMethod !== '') {
            $q->where('payment_method', $this->filterMethod);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterDateFrom !== '') {
            $q->whereDate('payment_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->whereDate('payment_date', '<=', $this->filterDateTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedPayments(): LengthAwarePaginator
    {
        return $this->paymentsQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Payment::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $payment = Payment::query()->findOrFail($id);
        Gate::authorize('update', $payment);

        $this->editingId         = $id;
        $this->amount            = (string) $payment->amount;
        $this->currency_code     = $payment->currency_code;
        $this->payment_date      = $payment->payment_date->format('Y-m-d');
        $this->due_date          = $payment->due_date?->format('Y-m-d') ?? '';
        $this->payment_method    = $payment->payment_method->value;
        $this->reference_no      = $payment->reference_no ?? '';
        $this->bank_account_id   = (string) ($payment->bank_account_id ?? '');
        $this->cash_register_id  = (string) ($payment->cash_register_id ?? '');
        $this->notes             = $payment->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::VOUCHERS_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'amount'           => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency_code'    => ['required', 'in:TRY,USD,EUR,GBP'],
            'payment_date'     => ['required', 'date'],
            'due_date'         => ['nullable', 'date'],
            'payment_method'   => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'reference_no'     => ['nullable', 'string', 'max:100'],
            'bank_account_id'  => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'amount'           => $validated['amount'],
            'currency_code'    => $validated['currency_code'],
            'payment_date'     => $validated['payment_date'],
            'due_date'         => filled($validated['due_date']) ? $validated['due_date'] : null,
            'payment_method'   => $validated['payment_method'],
            'reference_no'     => filled($validated['reference_no']) ? $validated['reference_no'] : null,
            'bank_account_id'  => filled($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null,
            'cash_register_id' => filled($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null,
            'notes'            => filled($validated['notes']) ? $validated['notes'] : null,
            'status'           => PaymentStatus::Pending->value,
        ];

        if ($this->editingId === 0) {
            Gate::authorize('create', Payment::class);
            Payment::query()->create($data);
            session()->flash('success', __('Payment created.'));
        } else {
            $payment = Payment::query()->findOrFail($this->editingId);
            Gate::authorize('update', $payment);
            $payment->update($data);
            session()->flash('success', __('Payment updated.'));
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    /** Maker-Checker: mark payment as completed */
    public function approve(int $id): void
    {
        $payment = Payment::query()->findOrFail($id);
        Gate::authorize('approve', $payment);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        $payment->update([
            'status'      => PaymentStatus::Completed->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        session()->flash('success', __('Payment #:id marked as completed.', ['id' => $id]));
    }

    public function delete(int $id): void
    {
        $payment = Payment::query()->findOrFail($id);
        Gate::authorize('delete', $payment);
        $payment->delete();
        session()->flash('success', __('Payment deleted.'));
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->amount           = '';
        $this->currency_code    = 'TRY';
        $this->payment_date     = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $this->due_date         = '';
        $this->payment_method   = 'bank_transfer';
        $this->reference_no     = '';
        $this->bank_account_id  = '';
        $this->cash_register_id = '';
        $this->notes            = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser   = auth()->user();
        $canWrite   = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VOUCHERS_WRITE);
        $canApprove = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    <x-admin.page-header
        :heading="__('Payments')"
        :description="__('Track and manage payments with Maker-Checker approval workflow.')"
    >
        <x-slot name="actions">
            <x-admin.index-actions>
                <x-slot name="export">
                    <flux:tooltip :content="__('Export CSV')" position="bottom">
                        <flux:button icon="arrow-down-tray" variant="outline" :href="route('admin.finance.payments.export.csv')" />
                    </flux:tooltip>
                </x-slot>
                @if ($canWrite)
                    <x-slot name="primary">
                        <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                            {{ __('New payment') }}
                        </flux:button>
                    </x-slot>
                @endif
            </x-admin.index-actions>
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
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending approval') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed (this month)') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['completed_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total paid (this year)') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">
                {{ number_format((float) $this->kpiStats['total_amount'], 2) }} ₺
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overdue') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['overdue'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->kpiStats['overdue'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Inline form --}}
    @if ($editingId !== null)
        <flux:card class="p-6">
            <flux:heading size="md" class="mb-4">
                {{ $editingId === 0 ? __('New payment') : __('Edit payment') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                    <flux:label>{{ __('Payment method') }} *</flux:label>
                    <flux:select wire:model="payment_method">
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="payment_method" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Payment date') }} *</flux:label>
                    <flux:input type="date" wire:model="payment_date" />
                    <flux:error name="payment_date" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Due date') }}</flux:label>
                    <flux:input type="date" wire:model="due_date" />
                    <flux:error name="due_date" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Reference no') }}</flux:label>
                    <flux:input type="text" wire:model="reference_no" />
                    <flux:error name="reference_no" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Bank account') }}</flux:label>
                    <flux:select wire:model="bank_account_id">
                        <option value="">— {{ __('None') }} —</option>
                        @foreach ($this->bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->bank_name }} — {{ $ba->account_number }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="bank_account_id" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Cash register') }}</flux:label>
                    <flux:select wire:model="cash_register_id">
                        <option value="">— {{ __('None') }} —</option>
                        @foreach ($this->cashRegisters as $cr)
                            <option value="{{ $cr->id }}">{{ $cr->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="cash_register_id" />
                </flux:field>
                <flux:field class="sm:col-span-2 lg:col-span-3">
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                    <flux:error name="notes" />
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
                :placeholder="__('Search reference, notes…')"
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
                    <flux:label>{{ __('Method') }}</flux:label>
                    <flux:select wire:model.live="filterMethod">
                        <option value="">{{ __('All') }}</option>
                        @foreach (\App\Enums\PaymentMethod::cases() as $m)
                            <option value="{{ $m->value }}">{{ $m->label() }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model.live="filterStatus">
                        <option value="">{{ __('All') }}</option>
                        @foreach (\App\Enums\PaymentStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Date from') }}</flux:label>
                    <flux:input type="date" wire:model.live="filterDateFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Date to') }}</flux:label>
                    <flux:input type="date" wire:model.live="filterDateTo" />
                </flux:field>
            </div>
            @if ($filterMethod || $filterStatus || $filterDateFrom || $filterDateTo)
                <div class="mt-2">
                    <flux:button variant="ghost" size="sm" wire:click="$set('filterMethod',''); $set('filterStatus',''); $set('filterDateFrom',''); $set('filterDateTo','')">
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
                wire:confirm="{{ __('Delete selected payments?') }}">
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
                    <th class="px-4 py-3 w-10">
                        <flux:checkbox wire:click="toggleSelectPage"
                            :checked="$this->isPageFullySelected()" />
                    </th>
                    <th class="px-4 py-3 cursor-pointer" wire:click="sortBy('id')">
                        #
                        @if ($sortColumn === 'id')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3 cursor-pointer" wire:click="sortBy('payment_date')">
                        {{ __('Date') }}
                        @if ($sortColumn === 'payment_date')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                    <th class="px-4 py-3 cursor-pointer" wire:click="sortBy('amount')">
                        {{ __('Amount') }}
                        @if ($sortColumn === 'amount')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3 cursor-pointer" wire:click="sortBy('payment_method')">
                        {{ __('Method') }}
                        @if ($sortColumn === 'payment_method')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3">{{ __('Due date') }}</th>
                    <th class="px-4 py-3 cursor-pointer" wire:click="sortBy('status')">
                        {{ __('Status') }}
                        @if ($sortColumn === 'status')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3">{{ __('Bank / Cash reg.') }}</th>
                    <th class="px-4 py-3">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->paginatedPayments as $payment)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-2">
                            <flux:checkbox wire:model.live="selectedIds" value="{{ $payment->id }}" />
                        </td>
                        <td class="px-4 py-2 text-zinc-400">{{ $payment->id }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $payment->payment_date->format('d.m.Y') }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $payment->reference_no ?? '—' }}</td>
                        <td class="px-4 py-2 font-semibold whitespace-nowrap">
                            {{ number_format((float) $payment->amount, 2) }}
                            <span class="text-xs text-zinc-400">{{ $payment->currency_code }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <flux:badge color="{{ $payment->payment_method->color() }}" size="sm">
                                {{ $payment->payment_method->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            @if ($payment->due_date)
                                <span class="{{ $payment->due_date->isPast() && $payment->status->isPending() ? 'text-red-500 font-semibold' : '' }}">
                                    {{ $payment->due_date->format('d.m.Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <flux:badge color="{{ $payment->status->color() }}" size="sm">
                                {{ $payment->status->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-2 text-xs text-zinc-500">
                            @if ($payment->bankAccount)
                                {{ $payment->bankAccount->bank_name }}
                            @elseif ($payment->cashRegister)
                                {{ $payment->cashRegister->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1">
                                @if ($canApprove && $payment->status->isPending())
                                    <flux:button size="sm" variant="primary"
                                        wire:click="approve({{ $payment->id }})"
                                        wire:confirm="{{ __('Mark payment #:id as completed?', ['id' => $payment->id]) }}">
                                        {{ __('Complete') }}
                                    </flux:button>
                                @endif
                                @if ($canWrite && $payment->status->isPending())
                                    <flux:button size="sm" variant="ghost" icon="pencil"
                                        wire:click="edit({{ $payment->id }})" />
                                @endif
                                @if ($canApprove && $payment->status->isPending())
                                    <flux:button size="sm" variant="danger" icon="trash"
                                        wire:click="delete({{ $payment->id }})"
                                        wire:confirm="{{ __('Delete this payment?') }}" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-zinc-400">
                            {{ __('No payments found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </flux:card>

    {{-- Pagination --}}
    <div>{{ $this->paginatedPayments->links() }}</div>
</div>
