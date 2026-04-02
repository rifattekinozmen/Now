<?php

use App\Authorization\LogisticsPermission;
use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\PersonnelAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Attendance')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $employee_id = '';
    public string $date        = '';
    public string $check_in   = '';
    public string $check_out  = '';
    public string $status      = 'present';
    public string $note        = '';

    // Filters
    public string $filterEmployee = '';
    public string $filterStatus   = '';
    public string $filterDate     = '';

    public bool $filtersOpen = false;

    public string $sortColumn = 'date';
    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', PersonnelAttendance::class);
        $this->date       = now()->format('Y-m-d');
        $this->filterDate = now()->format('Y-m-d');
    }

    public function updatedFilterEmployee(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDate(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['date', 'employee_id', 'status', 'check_in', 'check_out'];
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

    /**
     * @return array{today_present:int, today_absent:int, today_late:int, weekly_absences:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $today = now()->toDateString();

        return [
            'today_present'   => PersonnelAttendance::query()->forDate($today)->where('status', AttendanceStatus::Present->value)->count(),
            'today_absent'    => PersonnelAttendance::query()->forDate($today)->where('status', AttendanceStatus::Absent->value)->count(),
            'today_late'      => PersonnelAttendance::query()->forDate($today)->where('status', AttendanceStatus::Late->value)->count(),
            'weekly_absences' => PersonnelAttendance::query()
                ->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
                ->where('status', AttendanceStatus::Absent->value)
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

    private function attendanceQuery(): Builder
    {
        $q = PersonnelAttendance::query()->with(['employee', 'approvedBy']);

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', (int) $this->filterEmployee);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterDate !== '') {
            $q->whereDate('date', $this->filterDate);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderBy('employee_id');
    }

    #[Computed]
    public function paginatedAttendances(): LengthAwarePaginator
    {
        return $this->attendanceQuery()->paginate(25);
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedAttendances->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedAttendances->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', PersonnelAttendance::class);
        $count = PersonnelAttendance::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', PersonnelAttendance::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $record = PersonnelAttendance::query()->findOrFail($id);
        Gate::authorize('update', $record);

        $this->editingId   = $id;
        $this->employee_id = (string) $record->employee_id;
        $this->date        = $record->date->format('Y-m-d');
        $this->check_in   = $record->check_in ?? '';
        $this->check_out  = $record->check_out ?? '';
        $this->status      = $record->status->value;
        $this->note        = $record->note ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::EMPLOYEES_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date'        => ['required', 'date'],
            'check_in'   => ['nullable', 'date_format:H:i'],
            'check_out'  => ['nullable', 'date_format:H:i'],
            'status'      => ['required', 'in:present,absent,late,half_day'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $data = [
            'employee_id' => (int) $validated['employee_id'],
            'date'        => $validated['date'],
            'check_in'   => filled($validated['check_in']) ? $validated['check_in'] : null,
            'check_out'  => filled($validated['check_out']) ? $validated['check_out'] : null,
            'status'      => $validated['status'],
            'note'        => filled($validated['note']) ? $validated['note'] : null,
        ];

        if ($this->editingId === 0) {
            Gate::authorize('create', PersonnelAttendance::class);
            PersonnelAttendance::query()->create($data);
        } else {
            $record = PersonnelAttendance::query()->findOrFail($this->editingId);
            Gate::authorize('update', $record);
            $record->update($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $record = PersonnelAttendance::query()->findOrFail($id);
        Gate::authorize('approve', $record);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) {
            abort(403);
        }

        $record->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function delete(int $id): void
    {
        $record = PersonnelAttendance::query()->findOrFail($id);
        Gate::authorize('delete', $record);
        $record->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->employee_id = '';
        $this->date        = now()->format('Y-m-d');
        $this->check_in   = '';
        $this->check_out  = '';
        $this->status      = 'present';
        $this->note        = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser   = auth()->user();
        $canWrite   = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::EMPLOYEES_WRITE);
        $canApprove = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success">{{ session('bulk_deleted') }}</flux:callout>
    @endif

    <x-admin.page-header
        :heading="__('Attendance')"
        :description="__('Attendance tracking for employees.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('Add record') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today present') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['today_present'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today absent') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['today_absent'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->kpiStats['today_absent'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Late today') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['today_late'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['today_late'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Weekly absences') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['weekly_absences'] }}</flux:heading>
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
            <flux:select wire:model.live="filterEmployee" :label="__('Employee')" class="max-w-[220px]">
                <option value="">{{ __('All employees') }}</option>
                @foreach ($this->employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\AttendanceStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="filterDate" type="date" :label="__('Date')" class="max-w-[180px]" />
        @endif
    </x-admin.filter-bar>

    {{-- Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId === 0 ? __('Add attendance record') : __('Edit attendance record') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="employee_id" :label="__('Employee')" required>
                    <option value="">{{ __('Select employee...') }}</option>
                    @foreach ($this->employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="date" type="date" :label="__('Date')" required />
                <flux:select wire:model="status" :label="__('Status')" required>
                    @foreach (\App\Enums\AttendanceStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="check_in" type="time" :label="__('Check in')" />
                <flux:input wire:model="check_out" type="time" :label="__('Check out')" />
                <flux:input wire:model="note" :label="__('Notes')" />
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete bar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected records?') }}">{{ __('Delete selected') }}</flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">
                            <input type="checkbox"
                                wire:click="toggleSelectPage"
                                @checked($this->isPageFullySelected())
                            >
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Employee') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Date') }}@if ($sortColumn === 'date') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-center">
                            <button wire:click="sortBy('check_in')" class="mx-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Check in') }}@if ($sortColumn === 'check_in') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-center">
                            <button wire:click="sortBy('check_out')" class="mx-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Check out') }}@if ($sortColumn === 'check_out') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Notes') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Approved by') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAttendances as $record)
                        <tr>
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $record->id }}">
                            </td>
                            <td class="py-2 pe-3 font-medium">{{ $record->employee?->fullName() }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap">{{ $record->date->format('d M Y') }}</td>
                            <td class="py-2 pe-3 text-center font-mono">{{ $record->check_in ?? '—' }}</td>
                            <td class="py-2 pe-3 text-center font-mono">{{ $record->check_out ?? '—' }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $record->status->color() }}" size="sm">{{ $record->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 max-w-xs truncate text-zinc-500">{{ $record->note ?? '—' }}</td>
                            <td class="py-2 pe-3 text-xs text-zinc-500">
                                @if ($record->isApproved())
                                    {{ $record->approvedBy?->name ?? '—' }}
                                @else
                                    <span class="text-yellow-500">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    @if ($canApprove && ! $record->isApproved())
                                        <flux:button size="sm" variant="primary"
                                            wire:click="approve({{ $record->id }})"
                                            wire:confirm="{{ __('Approve attendance record?') }}"
                                        >{{ __('Approve') }}</flux:button>
                                    @endif
                                    @if ($canWrite)
                                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $record->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost"
                                            wire:click="delete({{ $record->id }})"
                                            wire:confirm="{{ __('Delete this attendance record?') }}"
                                        >{{ __('Delete') }}</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-500">
                                {{ __('No attendance records yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedAttendances->links() }}</div>
    </flux:card>
</div>
