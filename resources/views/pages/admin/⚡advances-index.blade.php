<?php

use App\Authorization\LogisticsPermission;
use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Advances')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $employee_id    = '';
    public string $amount         = '';
    public string $currency_code  = 'TRY';
    public string $requested_at   = '';
    public string $repayment_date = '';
    public string $reason         = '';

    // Filters
    public string $filterSearch   = '';
    public string $filterStatus   = '';
    public string $filterEmployee = '';

    public string $sortColumn = 'requested_at';
    public string $sortDirection = 'desc';

    public ?int $confirmingId = null;
    public string $confirmingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Advance::class);
        $this->requested_at   = now()->format('Y-m-d');
        $this->repayment_date = now()->addMonths(3)->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterEmployee(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'requested_at', 'amount', 'status', 'repayment_date'];
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
            'approve'  => $this->approve($this->confirmingId),
            'reject'   => $this->reject($this->confirmingId),
            'repaid'   => $this->markRepaid($this->confirmingId),
            'delete'   => $this->delete($this->confirmingId),
            default    => null,
        };
        $this->confirmingId = null;
        $this->confirmingAction = '';
    }

    /**
     * @return array{pending:int, approved_total:float, repaid_total:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'pending'       => Advance::query()->pending()->count(),
            'approved_total'=> (float) Advance::query()->approved()->sum('amount'),
            'repaid_total'  => (float) Advance::query()->where('status', AdvanceStatus::Repaid->value)->sum('amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    #[Computed]
    public function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::query()->orderBy('first_name')->get();
    }

    private function advancesQuery(): Builder
    {
        $q = Advance::query()->with(['employee', 'approvedBy']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->whereHas('employee', function (Builder $eq) use ($term): void {
                $eq->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term);
            });
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', (int) $this->filterEmployee);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedAdvances(): LengthAwarePaginator
    {
        return $this->advancesQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Advance::class);
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
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::ADVANCES_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'employee_id'    => ['required', 'integer', 'exists:employees,id'],
            'amount'         => ['required', 'numeric', 'min:1', 'max:9999999'],
            'currency_code'  => ['required', 'in:TRY,USD,EUR'],
            'requested_at'   => ['required', 'date'],
            'repayment_date' => ['nullable', 'date', 'after:requested_at'],
            'reason'         => ['nullable', 'string', 'max:500'],
        ]);

        Gate::authorize('create', Advance::class);
        Advance::query()->create([
            'employee_id'    => (int) $validated['employee_id'],
            'amount'         => $validated['amount'],
            'currency_code'  => $validated['currency_code'],
            'requested_at'   => $validated['requested_at'],
            'repayment_date' => filled($validated['repayment_date']) ? $validated['repayment_date'] : null,
            'status'         => AdvanceStatus::Pending->value,
            'reason'         => filled($validated['reason']) ? $validated['reason'] : null,
        ]);

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $advance = Advance::query()->findOrFail($id);
        Gate::authorize('approve', $advance);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        $advance->update([
            'status'      => AdvanceStatus::Approved->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(int $id): void
    {
        $advance = Advance::query()->findOrFail($id);
        Gate::authorize('approve', $advance);

        $advance->update([
            'status'           => AdvanceStatus::Rejected->value,
            'rejection_reason' => __('Rejected by admin.'),
        ]);
    }

    public function markRepaid(int $id): void
    {
        $advance = Advance::query()->findOrFail($id);
        Gate::authorize('update', $advance);

        $advance->update(['status' => AdvanceStatus::Repaid->value]);
    }

    public function delete(int $id): void
    {
        $advance = Advance::query()->findOrFail($id);
        Gate::authorize('delete', $advance);
        $advance->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->employee_id    = '';
        $this->amount         = '';
        $this->currency_code  = 'TRY';
        $this->requested_at   = now()->format('Y-m-d');
        $this->repayment_date = now()->addMonths(3)->format('Y-m-d');
        $this->reason         = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser   = auth()->user();
        $canWrite   = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::ADVANCES_WRITE);
        $canApprove = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    <x-admin.page-header
        :heading="__('Advances')"
        :description="__('Employee advance payment requests with Maker-Checker approval.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New advance request') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved total') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['approved_total'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Repaid total') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ number_format($this->kpiStats['repaid_total'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <x-admin.filter-bar :label="__('Filters')">
        <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search employee')" class="max-w-sm" />
        <flux:select wire:model.live="filterEmployee" :label="__('Employee')" class="max-w-[200px]">
            <option value="">{{ __('All employees') }}</option>
            @foreach ($this->employees as $emp)
                <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\AdvanceStatus::cases() as $as)
                <option value="{{ $as->value }}">{{ $as->label() }}</option>
            @endforeach
        </flux:select>
    </x-admin.filter-bar>

    {{-- Create Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New advance request') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="employee_id" :label="__('Employee')" required>
                    <option value="">{{ __('Select employee...') }}</option>
                    @foreach ($this->employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="amount" type="text" :label="__('Amount')" required placeholder="0.00" />
                <flux:select wire:model="currency_code" :label="__('Currency')">
                    <option value="TRY">TRY — Türk Lirası</option>
                    <option value="USD">USD — US Dollar</option>
                    <option value="EUR">EUR — Euro</option>
                </flux:select>
                <flux:input wire:model="requested_at" type="date" :label="__('Request date')" required />
                <flux:input wire:model="repayment_date" type="date" :label="__('Repayment date')" />
                <flux:textarea wire:model="reason" :label="__('Reason')" rows="2" class="lg:col-span-3" />
                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Employee') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('amount')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Amount') }}@if ($sortColumn === 'amount') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('requested_at')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Requested') }}@if ($sortColumn === 'requested_at') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('repayment_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Repayment') }}@if ($sortColumn === 'repayment_date') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Approved by') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAdvances as $adv)
                        <tr>
                            <td class="py-2 pe-3 font-medium">{{ $adv->employee?->fullName() }}</td>
                            <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$adv->amount, 2) }} {{ $adv->currency_code }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $adv->requested_at->format('d M Y') }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $adv->repayment_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $adv->status->color() }}" size="sm">{{ $adv->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-xs text-zinc-500">{{ $adv->approvedBy?->name ?? '—' }}</td>
                            <td class="py-2 text-end">
                                @if ($canApprove && $adv->status->isPending())
                                    <flux:button size="sm" variant="primary"
                                        wire:click="confirmAction({{ $adv->id }}, 'approve')"
                                    >{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="confirmAction({{ $adv->id }}, 'reject')"
                                    >{{ __('Reject') }}</flux:button>
                                @endif
                                @if ($canWrite && $adv->status->isApproved())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="confirmAction({{ $adv->id }}, 'repaid')"
                                    >{{ __('Mark repaid') }}</flux:button>
                                @endif
                                @if ($canWrite && $adv->status->isPending())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="confirmAction({{ $adv->id }}, 'delete')"
                                    >{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No advance requests yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedAdvances->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-action" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($confirmingAction === 'approve') {{ __('Approve this advance?') }}
                    @elseif ($confirmingAction === 'reject') {{ __('Reject?') }}
                    @elseif ($confirmingAction === 'repaid') {{ __('Mark as repaid?') }}
                    @else {{ __('Delete this advance?') }}
                    @endif
                </flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    :variant="in_array($confirmingAction, ['approve', 'repaid']) ? 'primary' : ($confirmingAction === 'delete' ? 'danger' : 'ghost')"
                    wire:click="executeAction"
                >
                    @if ($confirmingAction === 'approve') {{ __('Approve') }}
                    @elseif ($confirmingAction === 'reject') {{ __('Reject') }}
                    @elseif ($confirmingAction === 'repaid') {{ __('Mark repaid') }}
                    @else {{ __('Delete') }}
                    @endif
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
