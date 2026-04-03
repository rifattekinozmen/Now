<?php

use App\Authorization\LogisticsPermission;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Voucher;
use App\Services\Finance\VoucherApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Vouchers')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $cash_register_id = '';

    public string $order_id = '';

    public string $type = 'expense';

    public string $amount = '';

    public string $currency_code = 'TRY';

    public string $voucher_date = '';

    public string $reference_no = '';

    public string $description = '';

    public $documentFile = null;

    // Filters
    public bool $filtersOpen = false;

    public string $filterSearch = '';

    public string $filterType = '';

    public string $filterStatus = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public string $filterCashRegister = '';

    public string $sortColumn = 'voucher_date';
    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingId = null;
    public string $confirmingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Voucher::class);
        $this->voucher_date = now()->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }
    public function updatedFilterCashRegister(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'voucher_date', 'amount', 'type', 'status'];
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
        $pageIds = $this->paginatedVouchers->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedVouchers->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', Voucher::class);
        $count = Voucher::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function confirmAction(int $id, string $action): void
    {
        $this->confirmingId = $id;
        $this->confirmingAction = $action;
        $this->modal('confirm-action')->show();
    }

    public function executeAction(): void
    {
        if (! $this->confirmingId) {
            return;
        }
        match ($this->confirmingAction) {
            'approve' => $this->approve($this->confirmingId),
            'reject'  => $this->reject($this->confirmingId),
            default   => null,
        };
        $this->confirmingId = null;
        $this->confirmingAction = '';
    }

    /**
     * @return array{pending:int, approved_month:int, total_expense:float, total_income:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'pending'        => Voucher::query()->pending()->count(),
            'approved_month' => Voucher::query()->approved()
                ->whereMonth('approved_at', now()->month)->count(),
            'total_expense'  => Voucher::query()->approved()
                ->where('type', VoucherType::Expense->value)->sum('amount'),
            'total_income'   => Voucher::query()->approved()
                ->where('type', VoucherType::Income->value)->sum('amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CashRegister>
     */
    #[Computed]
    public function cashRegisters(): \Illuminate\Database\Eloquent\Collection
    {
        return CashRegister::query()->active()->orderBy('name')->get();
    }

    private function vouchersQuery(): Builder
    {
        $q = Voucher::query()->with(['cashRegister', 'order', 'approvedBy']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('reference_no', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterCashRegister !== '') {
            $q->where('cash_register_id', (int) $this->filterCashRegister);
        }

        if ($this->filterDateFrom !== '') {
            $q->whereDate('voucher_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->whereDate('voucher_date', '<=', $this->filterDateTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedVouchers(): LengthAwarePaginator
    {
        return $this->vouchersQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Voucher::class);
        $this->resetForm();
        $this->editingId = 0;
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
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'order_id'         => ['nullable', 'integer', 'exists:orders,id'],
            'type'             => ['required', 'in:expense,income,transfer'],
            'amount'           => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency_code'    => ['required', 'in:TRY,USD,EUR,GBP'],
            'voucher_date'     => ['required', 'date'],
            'reference_no'     => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ]);

        $documentPath = null;
        if ($this->documentFile) {
            $documentPath = $this->documentFile->store('voucher-documents', 'local');
        }

        $data = [
            'cash_register_id' => (int) $validated['cash_register_id'],
            'order_id'         => filled($validated['order_id']) ? (int) $validated['order_id'] : null,
            'type'             => $validated['type'],
            'amount'           => $validated['amount'],
            'currency_code'    => $validated['currency_code'],
            'voucher_date'     => $validated['voucher_date'],
            'reference_no'     => filled($validated['reference_no']) ? $validated['reference_no'] : null,
            'description'      => filled($validated['description']) ? $validated['description'] : null,
            'status'           => VoucherStatus::Pending->value, // always starts as pending
        ];

        if ($documentPath) {
            $data['document_path'] = $documentPath;
        }

        Gate::authorize('create', Voucher::class);
        Voucher::query()->create($data);

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    /** Maker-Checker: only logistics.admin can approve */
    public function approve(int $id, VoucherApprovalService $service): void
    {
        $voucher = Voucher::query()->findOrFail($id);
        Gate::authorize('approve', $voucher);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        try {
            $service->approve($voucher, $user);
            session()->flash('success', __('Voucher #:id approved.', ['id' => $id]));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** Maker-Checker: reject a pending voucher */
    public function reject(int $id, VoucherApprovalService $service): void
    {
        $voucher = Voucher::query()->findOrFail($id);
        Gate::authorize('approve', $voucher);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        $service->reject($voucher, $user, 'Rejected via admin panel');
        session()->flash('success', __('Voucher #:id rejected.', ['id' => $id]));
    }

    private function resetForm(): void
    {
        $this->cash_register_id = '';
        $this->order_id         = '';
        $this->type             = 'expense';
        $this->amount           = '';
        $this->currency_code    = 'TRY';
        $this->voucher_date     = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $this->reference_no     = '';
        $this->description      = '';
        $this->documentFile     = null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser       = auth()->user();
        $canWrite       = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VOUCHERS_WRITE);
        $canApprove     = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    <x-admin.page-header
        :heading="__('Vouchers')"
        :description="__('Expense, income and transfer vouchers with Maker-Checker approval workflow.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.cash-registers.index')" variant="ghost" wire:navigate>
                {{ __('Cash registers') }}
            </flux:button>
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New voucher') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- Flash messages --}}
    @if (session()->has('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif
    @if (session()->has('error'))
        <flux:callout variant="danger">{{ session('error') }}</flux:callout>
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
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved (this month)') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['approved_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total expenses (approved)') }}</flux:text>
            <flux:heading size="lg" class="text-red-500">
                {{ number_format((float) $this->kpiStats['total_expense'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total income (approved)') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">
                {{ number_format((float) $this->kpiStats['total_income'], 2) }}
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
            <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search ref / description')" class="max-w-sm" />
            <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[140px]">
                <option value="">{{ __('All types') }}</option>
                <option value="expense">{{ __('Expense') }}</option>
                <option value="income">{{ __('Income') }}</option>
                <option value="transfer">{{ __('Transfer') }}</option>
            </flux:select>
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
                <option value="">{{ __('All statuses') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="rejected">{{ __('Rejected') }}</option>
            </flux:select>
            <flux:select wire:model.live="filterCashRegister" :label="__('Cash register')" class="max-w-[200px]">
                <option value="">{{ __('All registers') }}</option>
                @foreach ($this->cashRegisters as $reg)
                    <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From')" class="max-w-[160px]" />
            <flux:input wire:model.live="filterDateTo" type="date" :label="__('To')" class="max-w-[160px]" />
        @endif
    </x-admin.filter-bar>

    {{-- Create Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New voucher') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="cash_register_id" :label="__('Cash register')" required>
                    <option value="">{{ __('Select register...') }}</option>
                    @foreach ($this->cashRegisters as $reg)
                        <option value="{{ $reg->id }}">{{ $reg->name }} ({{ $reg->currency_code }})</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="type" :label="__('Type')" required>
                    <option value="expense">{{ __('Expense') }}</option>
                    <option value="income">{{ __('Income') }}</option>
                    <option value="transfer">{{ __('Transfer') }}</option>
                </flux:select>
                <flux:input wire:model="amount" type="text" :label="__('Amount')" required placeholder="0.00" />
                <flux:select wire:model="currency_code" :label="__('Currency')" required>
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>
                <flux:input wire:model="voucher_date" type="date" :label="__('Voucher date')" required />
                <flux:input wire:model="reference_no" type="text" :label="__('Reference no')" placeholder="REF-001" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" class="lg:col-span-3" />

                {{-- Document upload --}}
                <div class="lg:col-span-3">
                    <flux:label>{{ __('Attach document (optional)') }}</flux:label>
                    <input type="file" wire:model="documentFile" accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1 block w-full text-sm text-zinc-700 dark:text-zinc-300" />
                    @error('documentFile')
                        <span class="text-xs text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>

            <flux:callout variant="info" class="mt-4">
                <flux:icon name="information-circle" class="size-4" />
                {{ __('Vouchers start as "Pending" and require admin approval before affecting the cash register balance.') }}
            </flux:callout>
        </flux:card>
    @endif

    {{-- Bulk delete toolbar --}}
    @if ($canWrite && count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected vouchers?') }}">{{ __('Delete selected') }}</flux:button>
        </div>
    @endif

    {{-- Vouchers Table --}}
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
                        <th class="py-2 pe-3 font-medium">{{ __('Ref No') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('voucher_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Date') }}@if ($sortColumn === 'voucher_date') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('type')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Type') }}@if ($sortColumn === 'type') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Cash Register') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('amount')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Amount') }}@if ($sortColumn === 'amount') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Order') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Approved by') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedVouchers as $voucher)
                        <tr>
                            @if ($canWrite)
                                <td class="py-2 pe-2 ps-2">
                                    <flux:checkbox wire:model.live="selectedIds" :value="(int) $voucher->id" />
                                </td>
                            @endif
                            <td class="py-2 pe-3 font-mono text-xs">
                                {{ $voucher->reference_no ?? '#'.$voucher->id }}
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap">
                                {{ $voucher->voucher_date->format('d M Y') }}
                            </td>
                            <td class="py-2 pe-3">
                                @php $type = $voucher->type; @endphp
                                <flux:badge color="{{ $type->color() }}" size="sm">
                                    {{ $type->label() }}
                                </flux:badge>
                            </td>
                            <td class="py-2 pe-3">{{ $voucher->cashRegister?->name ?? '—' }}</td>
                            <td class="py-2 pe-3 text-end font-mono">
                                <span class="{{ $voucher->type === \App\Enums\VoucherType::Expense ? 'text-red-500' : 'text-green-600' }}">
                                    {{ number_format((float) $voucher->amount, 2) }} {{ $voucher->currency_code }}
                                </span>
                            </td>
                            <td class="py-2 pe-3">
                                @php $status = $voucher->status; @endphp
                                <flux:badge color="{{ $status->color() }}" size="sm">
                                    {{ $status->label() }}
                                </flux:badge>
                            </td>
                            <td class="py-2 pe-3">
                                @if ($voucher->order)
                                    <a href="{{ route('admin.orders.show', $voucher->order) }}" wire:navigate
                                       class="text-primary text-xs hover:underline">
                                        {{ $voucher->order->order_number }}
                                    </a>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-xs text-zinc-500">
                                {{ $voucher->approvedBy?->name ?? '—' }}
                                @if ($voucher->approved_at)
                                    <br><span class="text-zinc-400">{{ $voucher->approved_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-end">
                                {{-- MAKER-CHECKER: approve/reject only shown to admin for pending vouchers --}}
                                @if ($canApprove && $voucher->status->isPending())
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        wire:click="confirmAction({{ $voucher->id }}, 'approve')"
                                    >{{ __('Approve') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="confirmAction({{ $voucher->id }}, 'reject')"
                                    >{{ __('Reject') }}</flux:button>
                                @endif
                                @if ($voucher->document_path)
                                    <flux:button size="sm" variant="ghost">{{ __('Doc') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canWrite ? 10 : 9 }}" class="py-8 text-center text-zinc-500">
                                {{ __('No vouchers found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedVouchers->links() }}
        </div>
    </flux:card>

    <flux:modal name="confirm-action" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($confirmingAction === 'approve')
                        {{ __('Approve this voucher?') }}
                    @else
                        {{ __('Reject this voucher?') }}
                    @endif
                </flux:heading>
                @if ($confirmingAction === 'approve')
                    <flux:text class="mt-2">{{ __('The cash register balance will be updated.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    :variant="$confirmingAction === 'approve' ? 'primary' : 'ghost'"
                    wire:click="executeAction"
                >
                    {{ $confirmingAction === 'approve' ? __('Approve') : __('Reject') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
