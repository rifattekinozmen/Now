<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Leaves')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $type       = 'annual';
    public string $start_date = '';
    public string $end_date   = '';
    public string $reason     = '';

    // Filter
    public string $filterStatus = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        if (! auth()->user()->employee_id) {
            abort(403);
        }
        $this->start_date = now()->format('Y-m-d');
        $this->end_date   = now()->addDay()->format('Y-m-d');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    private function employeeId(): int
    {
        return (int) auth()->user()->employee_id;
    }

    /**
     * @return array{pending:int, approved_this_year:int, total_days:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $eid = $this->employeeId();

        return [
            'pending'            => Leave::query()->where('employee_id', $eid)->where('status', LeaveStatus::Pending->value)->count(),
            'approved_this_year' => (int) Leave::query()
                ->where('employee_id', $eid)
                ->where('status', LeaveStatus::Approved->value)
                ->whereYear('start_date', now()->year)
                ->sum('days_count'),
            'total_days'         => (int) Leave::query()
                ->where('employee_id', $eid)
                ->where('status', LeaveStatus::Approved->value)
                ->sum('days_count'),
        ];
    }

    #[Computed]
    public function paginatedLeaves(): LengthAwarePaginator
    {
        $q = Leave::query()->where('employee_id', $this->employeeId());

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->orderByDesc('start_date')->paginate(15);
    }

    public function startCreate(): void
    {
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
        $validated = $this->validate([
            'type'       => ['required', 'in:annual,sick,unpaid,compensatory'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'reason'     => ['nullable', 'string', 'max:1000'],
        ]);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end   = \Carbon\Carbon::parse($validated['end_date']);
        $days  = (int) $start->diffInDays($end) + 1;

        // Only allow creating/editing pending leaves
        if ($this->editingId && $this->editingId > 0) {
            $leave = Leave::query()
                ->where('employee_id', $this->employeeId())
                ->where('status', LeaveStatus::Pending->value)
                ->findOrFail($this->editingId);

            $leave->update([
                'type'       => $validated['type'],
                'start_date' => $validated['start_date'],
                'end_date'   => $validated['end_date'],
                'days_count' => $days,
                'reason'     => filled($validated['reason']) ? $validated['reason'] : null,
            ]);
        } else {
            Leave::query()->create([
                'employee_id' => $this->employeeId(),
                'type'        => $validated['type'],
                'status'      => LeaveStatus::Pending->value,
                'start_date'  => $validated['start_date'],
                'end_date'    => $validated['end_date'],
                'days_count'  => $days,
                'reason'      => filled($validated['reason']) ? $validated['reason'] : null,
            ]);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
        session()->flash('saved', __('Leave request submitted.'));
    }

    public function startEdit(int $id): void
    {
        $leave = Leave::query()
            ->where('employee_id', $this->employeeId())
            ->where('status', LeaveStatus::Pending->value)
            ->findOrFail($id);

        $this->editingId  = $id;
        $this->type       = $leave->type->value;
        $this->start_date = $leave->start_date->format('Y-m-d');
        $this->end_date   = $leave->end_date->format('Y-m-d');
        $this->reason     = $leave->reason ?? '';
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            Leave::query()
                ->where('employee_id', $this->employeeId())
                ->where('status', LeaveStatus::Pending->value)
                ->findOrFail($this->confirmingDeleteId)
                ->delete();
            $this->confirmingDeleteId = null;
            $this->resetPage();
        }
    }

    private function resetForm(): void
    {
        $this->type       = 'annual';
        $this->start_date = now()->format('Y-m-d');
        $this->end_date   = now()->addDay()->format('Y-m-d');
        $this->reason     = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Leave Requests') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Request and track your leave.') }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:link :href="route('personnel.dashboard')" wire:navigate variant="ghost">
                ← {{ __('Dashboard') }}
            </flux:link>
            <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                {{ __('New request') }}
            </flux:button>
        </div>
    </div>

    @if (session('saved'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('saved') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved days (this year)') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['approved_this_year'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total approved days') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_days'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit request') : __('New leave request') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="type" :label="__('Leave type')">
                    @foreach (\App\Enums\LeaveType::cases() as $lt)
                        <option value="{{ $lt->value }}">{{ $lt->label() }}</option>
                    @endforeach
                </flux:select>
                <div>{{-- spacer --}}</div>
                <flux:input wire:model="start_date" type="date" :label="__('Start date')" required />
                <flux:input wire:model="end_date" type="date" :label="__('End date')" required />
                <flux:textarea wire:model="reason" :label="__('Reason (optional)')" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filter + Table --}}
    <flux:card class="p-4">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[180px]">
                <option value="">{{ __('All') }}</option>
                @foreach (\App\Enums\LeaveStatus::cases() as $ls)
                    <option value="{{ $ls->value }}">{{ $ls->label() }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Period') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Days') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Submitted') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedLeaves as $leave)
                        <tr>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $leave->type->color() }}" size="sm">{{ $leave->type->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">
                                {{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs">{{ $leave->days_count }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $leave->status->color() }}" size="sm">{{ $leave->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs text-zinc-500">
                                {{ $leave->created_at?->format('d M Y') }}
                            </td>
                            <td class="py-2 text-end">
                                @if ($leave->status->isPending())
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $leave->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $leave->id }})">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500">
                                {{ __('No leave requests yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedLeaves->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel leave request?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the pending request.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Go back') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
