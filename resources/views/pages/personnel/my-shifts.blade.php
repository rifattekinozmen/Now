<?php

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Shift;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Shifts')] class extends Component
{
    use WithPagination;

    public string $fromDate = '';
    public string $toDate = '';
    public string $filterStatus = '';

    public function mount(): void
    {
        if (! auth()->user()->employee_id) {
            abort(403);
        }
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->endOfMonth()->toDateString();
    }

    public function updatedFromDate(): void { $this->resetPage(); }

    public function updatedToDate(): void { $this->resetPage(); }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    private function employeeId(): int
    {
        return (int) auth()->user()->employee_id;
    }

    /**
     * @return array{total: int, upcoming: int, confirmed: int, absent: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $eid = $this->employeeId();

        return [
            'total' => Shift::query()
                ->where('employee_id', $eid)
                ->whereBetween('shift_date', [$this->fromDate ?: now()->startOfMonth()->toDateString(), $this->toDate ?: now()->endOfMonth()->toDateString()])
                ->count(),
            'upcoming' => Shift::query()
                ->where('employee_id', $eid)
                ->where('shift_date', '>=', now()->toDateString())
                ->whereIn('status', [ShiftStatus::Planned->value, ShiftStatus::Confirmed->value])
                ->count(),
            'confirmed' => Shift::query()
                ->where('employee_id', $eid)
                ->whereBetween('shift_date', [$this->fromDate ?: now()->startOfMonth()->toDateString(), $this->toDate ?: now()->endOfMonth()->toDateString()])
                ->where('status', ShiftStatus::Confirmed->value)
                ->count(),
            'absent' => Shift::query()
                ->where('employee_id', $eid)
                ->whereBetween('shift_date', [$this->fromDate ?: now()->startOfMonth()->toDateString(), $this->toDate ?: now()->endOfMonth()->toDateString()])
                ->where('status', ShiftStatus::Absent->value)
                ->count(),
        ];
    }

    #[Computed]
    public function paginatedShifts(): LengthAwarePaginator
    {
        $q = Shift::query()
            ->where('employee_id', $this->employeeId())
            ->orderBy('shift_date', 'desc')
            ->orderBy('start_time', 'asc');

        if ($this->fromDate !== '') {
            $q->where('shift_date', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $q->where('shift_date', '<=', $this->toDate);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->paginate(20);
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return collect(ShiftStatus::cases())
            ->mapWithKeys(fn (ShiftStatus $s): array => [$s->value => $s->label()])
            ->all();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My Shifts') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Your scheduled and past work shifts') }}</flux:text>
        </div>
        <flux:button :href="route('personnel.dashboard')" variant="ghost" wire:navigate icon="arrow-left">
            {{ __('Dashboard') }}
        </flux:button>
    </div>

    @if (session()->has('info'))
        <flux:callout variant="info">
            <flux:callout.text>{{ session('info') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Shifts (period)') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Upcoming') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['upcoming'] > 0 ? 'text-blue-600' : '' }}">
                {{ $this->kpiStats['upcoming'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Confirmed') }}</flux:text>
            <flux:heading size="lg" class="text-emerald-600">{{ $this->kpiStats['confirmed'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Absent') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['absent'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->kpiStats['absent'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <flux:input wire:model.live="fromDate" type="date" :label="__('From')" />
            <flux:input wire:model.live="toDate" type="date" :label="__('To')" />
            <div>
                <flux:select wire:model.live="filterStatus" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($this->statusOptions() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </flux:card>

    {{-- Shifts list --}}
    <flux:card class="p-4">
        @if ($this->paginatedShifts->isEmpty())
            <flux:text class="py-6 text-center text-sm text-zinc-500">{{ __('No shifts found for the selected period.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-4">{{ __('Date') }}</th>
                            <th class="pb-2 pr-4">{{ __('Type') }}</th>
                            <th class="pb-2 pr-4">{{ __('Time') }}</th>
                            <th class="pb-2 pr-4">{{ __('Status') }}</th>
                            <th class="pb-2">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->paginatedShifts as $shift)
                            @php $isToday = $shift->shift_date->isToday(); @endphp
                            <tr class="{{ $isToday ? 'bg-blue-50 dark:bg-blue-950/20' : '' }}">
                                <td class="py-3 pr-4">
                                    <span class="font-medium {{ $isToday ? 'text-blue-600' : '' }}">
                                        {{ $shift->shift_date->translatedFormat('d M Y') }}
                                    </span>
                                    @if ($isToday)
                                        <flux:badge size="sm" variant="outline" class="ml-1 !border-blue-400 !text-blue-600">{{ __('Today') }}</flux:badge>
                                    @endif
                                    <span class="block text-xs text-zinc-400">{{ $shift->shift_date->translatedFormat('l') }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    <flux:badge color="{{ $shift->shift_type->color() }}" size="sm">
                                        {{ $shift->shift_type->label() }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 pr-4 font-mono text-zinc-600 dark:text-zinc-300">
                                    {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                                </td>
                                <td class="py-3 pr-4">
                                    <flux:badge color="{{ $shift->status->color() }}" size="sm">
                                        {{ $shift->status->label() }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 max-w-[200px] truncate text-zinc-500" title="{{ $shift->notes }}">
                                    {{ $shift->notes ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->paginatedShifts->links() }}
            </div>
        @endif
    </flux:card>
</div>
