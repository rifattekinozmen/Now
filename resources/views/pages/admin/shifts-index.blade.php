<?php

use App\Authorization\LogisticsPermission;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Shifts')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $employee_id = '';
    public string $shift_date  = '';
    public string $start_time  = '08:00';
    public string $end_time    = '17:00';
    public string $shift_type  = 'regular';
    public string $status      = 'planned';
    public string $notes       = '';

    // Filters
    public string $filterSearch   = '';
    public string $filterType     = '';
    public string $filterStatus   = '';
    public string $filterEmployee = '';
    public string $filterDateFrom = '';
    public string $filterDateTo   = '';

    public bool $filtersOpen = false;

    public string $sortColumn    = 'shift_date';
    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    // Weekly view
    public string $viewMode  = 'list';
    public string $weekStart = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Shift::class);
        $this->shift_date  = now()->format('Y-m-d');
        $this->filterDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->filterDateTo   = now()->endOfMonth()->format('Y-m-d');
        $this->weekStart      = now()->startOfWeek()->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); $this->selectedIds = []; }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterEmployee(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'shift_date', 'start_time', 'shift_type', 'status', 'created_at'];
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
     * @return array{planned: int, confirmed: int, absent: int, this_month: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'planned'    => Shift::query()->where('status', ShiftStatus::Planned->value)->count(),
            'confirmed'  => Shift::query()->where('status', ShiftStatus::Confirmed->value)->count(),
            'absent'     => Shift::query()->where('status', ShiftStatus::Absent->value)->count(),
            'this_month' => Shift::query()
                ->whereMonth('shift_date', now()->month)
                ->whereYear('shift_date', now()->year)
                ->count(),
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

    private function shiftsQuery(): Builder
    {
        $q = Shift::query()->with('employee');

        if ($this->filterSearch !== '') {
            $term = '%' . addcslashes($this->filterSearch, '%_\\') . '%';
            $q->whereHas('employee', function (Builder $eq) use ($term): void {
                $eq->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('shift_type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', (int) $this->filterEmployee);
        }

        if ($this->filterDateFrom !== '') {
            $q->where('shift_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->where('shift_date', '<=', $this->filterDateTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedShifts(): LengthAwarePaginator
    {
        return $this->shiftsQuery()->paginate(20);
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedShifts->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedShifts->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', Shift::class);
        $count = Shift::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Shift::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $shift = Shift::query()->findOrFail($id);
        Gate::authorize('update', $shift);

        $this->editingId  = $id;
        $this->employee_id = (string) $shift->employee_id;
        $this->shift_date  = $shift->shift_date->format('Y-m-d');
        $this->start_time  = substr((string) $shift->start_time, 0, 5);
        $this->end_time    = substr((string) $shift->end_time, 0, 5);
        $this->shift_type  = $shift->shift_type->value;
        $this->status      = $shift->status->value;
        $this->notes       = $shift->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::SHIFTS_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shift_date'  => ['required', 'date'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i'],
            'shift_type'  => ['required', 'in:' . implode(',', array_column(ShiftType::cases(), 'value'))],
            'status'      => ['required', 'in:' . implode(',', array_column(ShiftStatus::cases(), 'value'))],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'employee_id' => (int) $validated['employee_id'],
            'shift_date'  => $validated['shift_date'],
            'start_time'  => $validated['start_time'] . ':00',
            'end_time'    => $validated['end_time'] . ':00',
            'shift_type'  => $validated['shift_type'],
            'status'      => $validated['status'],
            'notes'       => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId === 0) {
            Gate::authorize('create', Shift::class);
            Shift::query()->create($data);
        } else {
            $shift = Shift::query()->findOrFail($this->editingId);
            Gate::authorize('update', $shift);
            $shift->update($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function confirm(int $id): void
    {
        $shift = Shift::query()->findOrFail($id);
        Gate::authorize('update', $shift);
        $shift->update(['status' => ShiftStatus::Confirmed->value]);
    }

    public function markAbsent(int $id): void
    {
        $shift = Shift::query()->findOrFail($id);
        Gate::authorize('update', $shift);
        $shift->update(['status' => ShiftStatus::Absent->value]);
    }

    public function delete(int $id): void
    {
        $shift = Shift::query()->findOrFail($id);
        Gate::authorize('delete', $shift);
        $shift->delete();
        $this->resetPage();
    }

    public function prevWeek(): void
    {
        $this->weekStart = \Carbon\Carbon::parse($this->weekStart)->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = \Carbon\Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d');
    }

    /**
     * @return array<int, array{employee: Employee, days: array<string, list<\App\Models\Shift>>}>
     */
    #[Computed]
    public function weeklyGrid(): array
    {
        $start = \Carbon\Carbon::parse($this->weekStart)->startOfWeek();
        $end   = $start->copy()->endOfWeek();

        $shifts = Shift::query()
            ->with('employee')
            ->whereBetween('shift_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('start_time')
            ->get();

        $byEmployee = [];
        foreach ($shifts as $shift) {
            $eid = $shift->employee_id;
            if (! isset($byEmployee[$eid])) {
                $byEmployee[$eid] = [
                    'employee' => $shift->employee,
                    'days'     => [],
                ];
            }
            $dateKey = $shift->shift_date->format('Y-m-d');
            $byEmployee[$eid]['days'][$dateKey][] = $shift;
        }

        return array_values($byEmployee);
    }

    private function resetForm(): void
    {
        $this->employee_id = '';
        $this->shift_date  = now()->format('Y-m-d');
        $this->start_time  = '08:00';
        $this->end_time    = '17:00';
        $this->shift_type  = 'regular';
        $this->status      = 'planned';
        $this->notes       = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::SHIFTS_WRITE);
    @endphp

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success">{{ session('bulk_deleted') }}</flux:callout>
    @endif

    <x-admin.page-header
        :heading="__('Shifts')"
        :description="__('Plan and track employee work shifts.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New Shift') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- View mode toggle --}}
    <div class="flex items-center gap-2">
        <flux:button
            type="button"
            size="sm"
            :variant="$viewMode === 'list' ? 'primary' : 'ghost'"
            wire:click="$set('viewMode', 'list')"
            icon="list-bullet"
        >{{ __('List view') }}</flux:button>
        <flux:button
            type="button"
            size="sm"
            :variant="$viewMode === 'week' ? 'primary' : 'ghost'"
            wire:click="$set('viewMode', 'week')"
            icon="calendar-days"
        >{{ __('Week view') }}</flux:button>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Planned') }}</span>
            <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->kpiStats['planned'] }}</span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Confirmed') }}</span>
            <span class="text-2xl font-bold text-green-600">{{ $this->kpiStats['confirmed'] }}</span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Absent') }}</span>
            <span class="text-2xl font-bold text-red-600">{{ $this->kpiStats['absent'] }}</span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This Month') }}</span>
            <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->kpiStats['this_month'] }}</span>
        </flux:card>
    </div>

    @if ($viewMode === 'week')
        {{-- Weekly planning calendar --}}
        @php
            $weekStartCarbon = \Carbon\Carbon::parse($weekStart)->startOfWeek();
            $weekDays = collect(range(0, 6))->map(fn ($i) => $weekStartCarbon->copy()->addDays($i));
            $today = now()->format('Y-m-d');
        @endphp

        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <flux:button type="button" size="sm" variant="ghost" wire:click="prevWeek" icon="chevron-left">
                    {{ __('Prev') }}
                </flux:button>
                <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                    {{ __('Week of :date', ['date' => $weekStartCarbon->format('d M Y')]) }}
                </span>
                <flux:button type="button" size="sm" variant="ghost" wire:click="nextWeek" icon-trailing="chevron-right">
                    {{ __('Next') }}
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 pe-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('Employee') }}
                            </th>
                            @foreach ($weekDays as $day)
                                <th class="min-w-[110px] px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider
                                    {{ $day->format('Y-m-d') === $today ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                    {{ $day->format('D') }}<br>
                                    <span class="font-normal">{{ $day->format('d M') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->weeklyGrid as $row)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="py-2 pe-4 font-medium text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                    {{ $row['employee']->first_name }} {{ $row['employee']->last_name }}
                                </td>
                                @foreach ($weekDays as $day)
                                    @php $dayKey = $day->format('Y-m-d'); @endphp
                                    <td class="px-2 py-2 text-center align-top
                                        {{ $dayKey === $today ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                        @if (!empty($row['days'][$dayKey]))
                                            @foreach ($row['days'][$dayKey] as $s)
                                                <div class="mb-1">
                                                    <flux:badge color="{{ $s->shift_type->color() }}" size="sm" class="block w-full truncate text-center">
                                                        {{ substr((string) $s->start_time, 0, 5) }}–{{ substr((string) $s->end_time, 0, 5) }}
                                                    </flux:badge>
                                                    <span class="mt-0.5 block text-[10px] text-zinc-400">{{ $s->status->label() }}</span>
                                                </div>
                                            @endforeach
                                        @else
                                            <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-400 dark:text-zinc-500">
                                    {{ __('No shifts found for this week.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @else
    {{-- Inline form --}}
    @if ($editingId !== null)
        <flux:card class="p-6">
            <h3 class="mb-4 text-base font-semibold text-zinc-800 dark:text-zinc-200">
                {{ $editingId === 0 ? __('New Shift') : __('Edit Shift') }}
            </h3>
            <form wire:submit.prevent="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Employee') }}</flux:label>
                    <flux:select wire:model="employee_id">
                        <option value="">{{ __('Select employee') }}</option>
                        @foreach ($this->employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="employee_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Shift Date') }}</flux:label>
                    <flux:input type="date" wire:model="shift_date" />
                    <flux:error name="shift_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Shift Type') }}</flux:label>
                    <flux:select wire:model="shift_type">
                        @foreach (\App\Enums\ShiftType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="shift_type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Start Time') }}</flux:label>
                    <flux:input type="time" wire:model="start_time" />
                    <flux:error name="start_time" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('End Time') }}</flux:label>
                    <flux:input type="time" wire:model="end_time" />
                    <flux:error name="end_time" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="status">
                        @foreach (\App\Enums\ShiftStatus::cases() as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>

                <flux:field class="sm:col-span-2 lg:col-span-3">
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                    <flux:error name="notes" />
                </flux:field>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" wire:click="cancelForm" variant="ghost">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="filterSearch"
                placeholder="{{ __('Search employee…') }}"
                icon="magnifying-glass"
                class="w-56"
            />
            <flux:input type="date" wire:model.live="filterDateFrom" class="w-40" />
            <span class="text-zinc-400">–</span>
            <flux:input type="date" wire:model.live="filterDateTo" class="w-40" />
            <flux:select wire:model.live="filterType" class="w-40">
                <option value="">{{ __('All Types') }}</option>
                @foreach (\App\Enums\ShiftType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterStatus" class="w-40">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach (\App\Enums\ShiftStatus::cases() as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterEmployee" class="w-48">
                <option value="">{{ __('All Employees') }}</option>
                @foreach ($this->employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- Bulk actions --}}
    @if (count($selectedIds) > 0 && $canWrite)
        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 dark:border-red-800 dark:bg-red-950">
            <span class="text-sm text-red-700 dark:text-red-300">
                {{ __(':count selected', ['count' => count($selectedIds)]) }}
            </span>
            <flux:button size="sm" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected shifts?') }}">
                {{ __('Delete Selected') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                        @if ($canWrite)
                            <th class="px-4 py-3">
                                <flux:checkbox wire:click="toggleSelectPage" :checked="$this->isPageFullySelected()" />
                            </th>
                        @endif
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('shift_date')">
                            {{ __('Date') }}
                            @if ($sortColumn === 'shift_date')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3">{{ __('Employee') }}</th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('start_time')">
                            {{ __('Hours') }}
                        </th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('shift_type')">
                            {{ __('Type') }}
                        </th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('status')">
                            {{ __('Status') }}
                        </th>
                        <th class="px-4 py-3">{{ __('Notes') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedShifts as $shift)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="shift-{{ $shift->id }}">
                            @if ($canWrite)
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selectedIds" value="{{ $shift->id }}" />
                                </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $shift->shift_date->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $shift->employee->first_name }} {{ $shift->employee->last_name }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ substr((string) $shift->start_time, 0, 5) }} – {{ substr((string) $shift->end_time, 0, 5) }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $shift->shift_type->color() }}" size="sm">
                                    {{ $shift->shift_type->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $shift->status->color() }}" size="sm">
                                    {{ $shift->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $shift->notes ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($canWrite && $shift->status->isPlanned())
                                        <flux:button size="xs" variant="ghost" wire:click="confirm({{ $shift->id }})" title="{{ __('Confirm') }}">
                                            <flux:icon name="check" variant="micro" />
                                        </flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="markAbsent({{ $shift->id }})" title="{{ __('Mark Absent') }}">
                                            <flux:icon name="x-mark" variant="micro" />
                                        </flux:button>
                                    @endif
                                    @if ($canWrite)
                                        <flux:button size="xs" variant="ghost" wire:click="startEdit({{ $shift->id }})" title="{{ __('Edit') }}">
                                            <flux:icon name="pencil-square" variant="micro" />
                                        </flux:button>
                                    @endif
                                    @can('delete', $shift)
                                        <flux:button size="xs" variant="ghost" wire:click="delete({{ $shift->id }})" wire:confirm="{{ __('Delete this shift?') }}" title="{{ __('Delete') }}">
                                            <flux:icon name="trash" variant="micro" class="text-red-500" />
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canWrite ? 8 : 7 }}" class="px-4 py-8 text-center text-zinc-400 dark:text-zinc-500">
                                {{ __('No shifts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->paginatedShifts->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->paginatedShifts->links() }}
            </div>
        @endif
    </flux:card>
    @endif {{-- end @else (list view) --}}
</div>
