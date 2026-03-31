<?php

use App\Authorization\LogisticsPermission;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Leave Requests')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $employee_id  = '';
    public string $type         = 'annual';
    public string $start_date   = '';
    public string $end_date     = '';
    public string $reason       = '';

    // Filters
    public string $filterSearch    = '';
    public string $filterType      = '';
    public string $filterStatus    = '';
    public string $filterEmployee  = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Leave::class);
        $this->start_date = now()->format('Y-m-d');
        $this->end_date   = now()->addDay()->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterEmployee(): void { $this->resetPage(); }

    /**
     * @return array{pending:int, approved_this_month:int, total_days:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'pending'             => Leave::query()->pending()->count(),
            'approved_this_month' => Leave::query()->approved()
                ->whereMonth('start_date', now()->month)->count(),
            'total_days'          => (int) Leave::query()->approved()->sum('days_count'),
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

    private function leavesQuery(): Builder
    {
        $q = Leave::query()->with(['employee', 'approvedBy']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->whereHas('employee', function (Builder $eq) use ($term): void {
                $eq->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', (int) $this->filterEmployee);
        }

        return $q->orderByDesc('start_date')->orderByDesc('id');
    }

    #[Computed]
    public function paginatedLeaves(): LengthAwarePaginator
    {
        return $this->leavesQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Leave::class);
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
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::LEAVES_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type'        => ['required', 'in:annual,sick,unpaid,compensatory'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        $start  = \Carbon\CarbonImmutable::parse($validated['start_date']);
        $end    = \Carbon\CarbonImmutable::parse($validated['end_date']);
        $days   = (int) $start->diffInDays($end) + 1;

        Gate::authorize('create', Leave::class);
        Leave::query()->create([
            'employee_id' => (int) $validated['employee_id'],
            'type'        => $validated['type'],
            'status'      => LeaveStatus::Pending->value,
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'days_count'  => $days,
            'reason'      => filled($validated['reason']) ? $validated['reason'] : null,
        ]);

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    /** Maker-Checker: Admin onaylar */
    public function approve(int $id): void
    {
        $leave = Leave::query()->findOrFail($id);
        Gate::authorize('approve', $leave);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        $leave->update([
            'status'      => LeaveStatus::Approved->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    /** Maker-Checker: Admin reddeder */
    public function reject(int $id, string $reason = ''): void
    {
        $leave = Leave::query()->findOrFail($id);
        Gate::authorize('approve', $leave);

        $leave->update([
            'status'           => LeaveStatus::Rejected->value,
            'rejection_reason' => filled($reason) ? $reason : __('Rejected by admin.'),
        ]);
    }

    public function delete(int $id): void
    {
        $leave = Leave::query()->findOrFail($id);
        Gate::authorize('delete', $leave);
        $leave->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->employee_id = '';
        $this->type        = 'annual';
        $this->start_date  = now()->format('Y-m-d');
        $this->end_date    = now()->addDay()->format('Y-m-d');
        $this->reason      = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser   = auth()->user();
        $canWrite   = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::LEAVES_WRITE);
        $canApprove = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    <x-admin.page-header
        :heading="__('Leave Requests')"
        :description="__('Manage employee leave requests with Maker-Checker approval workflow.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New request') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending approval') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved this month') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['approved_this_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total approved days') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_days'] }}</flux:heading>
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
        <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[160px]">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\LeaveType::cases() as $lt)
                <option value="{{ $lt->value }}">{{ $lt->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\LeaveStatus::cases() as $ls)
                <option value="{{ $ls->value }}">{{ $ls->label() }}</option>
            @endforeach
        </flux:select>
    </x-admin.filter-bar>

    {{-- Create Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New leave request') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="employee_id" :label="__('Employee')" required>
                    <option value="">{{ __('Select employee...') }}</option>
                    @foreach ($this->employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="type" :label="__('Leave type')" required>
                    @foreach (\App\Enums\LeaveType::cases() as $lt)
                        <option value="{{ $lt->value }}">{{ $lt->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="start_date" type="date" :label="__('Start date')" required />
                <flux:input wire:model="end_date" type="date" :label="__('End date')" required />
                <flux:textarea wire:model="reason" :label="__('Reason (optional)')" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
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
                        <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Start') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('End') }}</th>
                        <th class="py-2 pe-3 font-medium text-center">{{ __('Days') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Approved by') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedLeaves as $leave)
                        <tr>
                            <td class="py-2 pe-3 font-medium">{{ $leave->employee?->fullName() }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $leave->type->color() }}" size="sm">{{ $leave->type->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $leave->start_date->format('d M Y') }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $leave->end_date->format('d M Y') }}</td>
                            <td class="py-2 pe-3 text-center font-mono">{{ $leave->days_count }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $leave->status->color() }}" size="sm">{{ $leave->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-xs text-zinc-500">
                                {{ $leave->approvedBy?->name ?? '—' }}
                            </td>
                            <td class="py-2 text-end">
                                @if ($canApprove && $leave->status->isPending())
                                    <flux:button size="sm" variant="primary"
                                        wire:click="approve({{ $leave->id }})"
                                        wire:confirm="{{ __('Approve this leave request?') }}"
                                    >{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="reject({{ $leave->id }})"
                                        wire:confirm="{{ __('Reject this leave request?') }}"
                                    >{{ __('Reject') }}</flux:button>
                                @endif
                                @if ($canWrite && $leave->status->isPending())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="delete({{ $leave->id }})"
                                        wire:confirm="{{ __('Delete this leave request?') }}"
                                    >{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No leave requests yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedLeaves->links() }}</div>
    </flux:card>
</div>
